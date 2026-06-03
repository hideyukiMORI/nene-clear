import { test, expect } from '@playwright/test'
import { apiRoute, json, problem, list, bypassLogin, dunningNotice, upstreamInvoice } from '../fixtures/helpers'

/**
 * Dunning is driven by the eligible-invoices list: each overdue / partially
 * paid invoice has a "送信" action that opens a confirm modal, and the POST is
 * fired from inside that modal. There is no free-text invoice-id field.
 */
test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  await apiRoute(page, '**/admin/dunning-notices?**', (route) => json(route, 200, list([])))
  await apiRoute(page, '**/admin/dunning-pauses?**', (route) => json(route, 200, list([])))
})

test.describe('Dunning — send boundaries', () => {
  test('no eligible invoices shows the empty state', async ({ page }) => {
    await apiRoute(page, '**/admin/invoices?**', (route) => json(route, 200, { items: [], total: 0 }))

    await page.goto('/admin/dunning')

    await expect(page.getByText('督促対象の請求書はありません')).toBeVisible()
  })

  test('invoice not eligible (422) surfaces the upstream error', async ({ page }) => {
    await apiRoute(page, '**/admin/invoices?**', (route) =>
      json(route, 200, { items: [upstreamInvoice({ invoice_id: 123 })], total: 1 }),
    )
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        return problem(route, 422, 'invoice-not-eligible-for-dunning', 'この請求書は支払済み、または残高がないため督促できません。')
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.getByRole('button', { name: '督促を送信' }).first().click()
    await page.getByRole('dialog').getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByText('この請求書は支払済み、または残高がないため督促できません。')).toBeVisible()
  })

  test('too frequent (422) surfaces the interval error', async ({ page }) => {
    await apiRoute(page, '**/admin/invoices?**', (route) =>
      json(route, 200, { items: [upstreamInvoice({ invoice_id: 123 })], total: 1 }),
    )
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        return problem(route, 422, 'dunning-too-frequent', '前回の督促から最短間隔が経過していません。', undefined, {
          next_allowed_at: '2026-05-01 09:00:00',
        })
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.getByRole('button', { name: '督促を送信' }).first().click()
    await page.getByRole('dialog').getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByText('前回の督促から最短間隔が経過していません。')).toBeVisible()
  })

  test('successful send closes the dialog and lists the notice in history', async ({ page }) => {
    let sent = false
    await apiRoute(page, '**/admin/invoices?**', (route) =>
      json(route, 200, { items: [upstreamInvoice({ invoice_id: 123, invoice_number: 'INV-2026-009' })], total: 1 }),
    )
    // History reflects the send once the POST has happened (cache invalidation refetches).
    await apiRoute(page, '**/admin/dunning-notices?**', (route) =>
      json(route, 200, list(sent ? [dunningNotice({ invoice_number: 'INV-2026-009' })] : [])),
    )
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        sent = true
        return json(route, 201, dunningNotice({ invoice_number: 'INV-2026-009' }))
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.getByRole('button', { name: '督促を送信' }).first().click()
    await page.getByRole('dialog').getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByRole('dialog')).toHaveCount(0)
    // The recipient email only appears in the history table, so it uniquely
    // confirms the sent notice was listed (the invoice number also shows in the
    // eligible-invoices table above).
    await expect(page.getByText('accounts@acme.example')).toBeVisible()
  })
})
