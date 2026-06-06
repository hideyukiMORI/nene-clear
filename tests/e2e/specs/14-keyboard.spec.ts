import { test, expect } from '@playwright/test'
import { apiRoute, json, list, user, bypassLogin } from '../fixtures/helpers'

/**
 * Keyboard shortcuts + command palette, wired through the real AppShell mount.
 * The dispatcher logic itself is unit-tested; this verifies the in-browser
 * mounting, focus rules, and navigation end to end.
 */
test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
    json(route, 200, { items: [], total: 0, limit: 1, offset: 0 }),
  )
  await apiRoute(page, '**/admin/dunning-notices**', (route) => json(route, 200, list([])))
  // Reconciliation list endpoints (g → r target) — keep noise down.
  await apiRoute(page, '**/admin/reconciliations**', (route) => json(route, 200, list([])))
  await apiRoute(page, '**/admin/bank-transactions**', (route) => json(route, 200, list([])))
})

test.describe('Keyboard shortcuts', () => {
  test('? opens the cheat-sheet overlay; Esc closes it', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('button', { name: 'ログアウト' })).toBeVisible()

    await page.keyboard.press('?')
    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText('キーボードショートカット')).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(page.getByRole('dialog')).toHaveCount(0)
  })

  test('g → r navigates to reconciliation', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('button', { name: 'ログアウト' })).toBeVisible()

    await page.keyboard.press('g')
    await page.keyboard.press('r')
    await expect(page).toHaveURL(/\/admin\/reconciliation/)
  })

  test('Ctrl+K opens the palette; j then Enter navigates', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('button', { name: 'ログアウト' })).toBeVisible()

    await page.keyboard.press('Control+k')
    await expect(page.getByRole('listbox')).toBeVisible()

    // Cursor starts at the first command (dashboard); j → reconciliation.
    await page.keyboard.press('j')
    await page.keyboard.press('Enter')
    await expect(page).toHaveURL(/\/admin\/reconciliation/)
    await expect(page.getByRole('listbox')).toHaveCount(0)
  })

  test('/ focuses the list search field, not a navigation', async ({ page }) => {
    await page.goto('/admin/bank-transactions')
    await expect(page.getByRole('button', { name: 'ログアウト' })).toBeVisible()

    await page.keyboard.press('/')
    await expect(page.locator('[data-kbd="search"]')).toBeFocused()
  })

  test('list cursor: j highlights a row, Enter opens its action', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) =>
      json(
        route,
        200,
        list([
          user({ user_id: 1, email: 'a@acme.example', role: 'admin', status: 'active' }),
          user({ user_id: 2, email: 'b@acme.example', role: 'viewer', status: 'active' }),
        ]),
      ),
    )
    await page.goto('/admin/users')
    await expect(page.getByText('a@acme.example')).toBeVisible()

    // No cursor until j; then the first row is highlighted.
    await expect(page.locator('tr.is-cursor')).toHaveCount(0)
    await page.keyboard.press('j')
    await expect(page.locator('tr.is-cursor')).toHaveCount(1)
    await expect(page.getByRole('row', { name: /a@acme.example/ })).toHaveClass(/is-cursor/)

    // Enter opens the cursored row's primary action (delete confirmation).
    await page.keyboard.press('Enter')
    await expect(page.getByRole('dialog')).toBeVisible()
  })
})
