import { test, expect } from '@playwright/test'
import {
  apiRoute, json, problem, list, loginViaForm, navTo, logout, dunningNotice, upstreamInvoice,
} from '../fixtures/helpers'

/**
 * Scenario 2 — Dunning with a mid-session locale switch.
 *
 * login → switch to EN → send dunning for an eligible invoice (ok) → send again
 * (422 too-frequent) → switch back to JA → history still shows the sent notice →
 * logout.
 *
 * Bug surface: locale switching mid-flow without losing list/modal state, error
 * message rendering, and history refresh after a successful send.
 */
test('scenario: locale switch + dunning send + too-frequent + logout', async ({ page }) => {
  let notices: ReturnType<typeof dunningNotice>[] = []
  let sendCount = 0

  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
    json(route, 200, { items: [], total: 3, limit: 1, offset: 0 }),
  )
  await apiRoute(page, '**/admin/upstream/invoices?**', (route) =>
    json(route, 200, { items: [upstreamInvoice({ invoice_id: 123, invoice_number: 'INV-2026-123' })], total: 1 }),
  )
  await apiRoute(page, '**/admin/dunning-pauses?**', (route) => json(route, 200, list([])))
  await apiRoute(page, '**/admin/dunning-notices?**', (route) => json(route, 200, list(notices)))
  await apiRoute(page, '**/admin/dunning-notices', (route) => {
    if (route.request().method() === 'POST') {
      sendCount += 1
      if (sendCount === 1) {
        notices = [dunningNotice({ invoice_number: 'INV-2026-123', invoice_id: 123 })]
        return json(route, 201, notices[0])
      }
      // Second send for the same invoice is blocked by the interval.
      return problem(route, 422, 'dunning-too-frequent', '前回の督促から最短間隔が経過していません。', undefined, {
        next_allowed_at: '2026-05-08 09:00:00',
      })
    }
    return json(route, 200, list(notices))
  })

  // ── 1. Login (JA default) ───────────────────────────────────────────────
  await loginViaForm(page)
  await expect(page.getByRole('link', { name: 'ダッシュボード' })).toBeVisible()

  // ── 2. Switch to English ────────────────────────────────────────────────
  await page.getByRole('button', { name: 'EN' }).click()
  await expect(page.getByRole('link', { name: 'Dunning' })).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')

  // ── 3. Navigate to dunning and send for the eligible invoice ────────────
  await navTo(page, 'Dunning', /\/admin\/dunning/)
  await page.getByRole('button', { name: 'Send dunning' }).first().click()
  await page.getByRole('dialog').getByRole('button', { name: 'Send dunning' }).click()
  // Modal closes and the sent notice appears in history (cache invalidation).
  // The recipient email is unique to the history table (the invoice number also
  // shows in the eligible-invoices list above).
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('accounts@acme.example')).toBeVisible()

  // ── 4. Send again → 422 too-frequent error inside the modal ─────────────
  await page.getByRole('button', { name: 'Send dunning' }).first().click()
  await page.getByRole('dialog').getByRole('button', { name: 'Send dunning' }).click()
  await expect(page.getByText('前回の督促から最短間隔が経過していません。')).toBeVisible()
  // Dismiss the modal (Escape is locale-independent).
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toHaveCount(0)

  // ── 5. Switch back to JA — nav + history survive the locale flip ─────────
  await page.getByRole('button', { name: 'JA' }).click()
  await expect(page.getByRole('link', { name: '督促' })).toBeVisible()
  await expect(page.getByText('accounts@acme.example')).toBeVisible()

  // ── 6. Logout ───────────────────────────────────────────────────────────
  await logout(page)
  await page.goto('/admin')
  await expect(page).toHaveURL(/\/login/)
})
