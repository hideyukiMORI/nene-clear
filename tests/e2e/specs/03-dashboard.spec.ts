import { test, expect } from '@playwright/test'
import { apiRoute, json, list, bypassLogin, dunningNotice } from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
})

test.describe('Dashboard', () => {
  test('shows unmatched count and recent dunning', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      json(route, 200, { items: [], total: 7, limit: 1, offset: 0 }),
    )
    await apiRoute(page, '**/admin/dunning-notices**', (route) =>
      json(route, 200, list([dunningNotice({ invoice_number: 'INV-2026-009' })])),
    )

    await page.goto('/admin')

    await expect(page.locator('p.text-4xl')).toContainText('7')
    await expect(page.getByText('INV-2026-009')).toBeVisible()
    await expect(page.getByText('¥1,100')).toBeVisible()
  })

  test('shows zero and no-data state when empty', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      json(route, 200, { items: [], total: 0, limit: 1, offset: 0 }),
    )
    await apiRoute(page, '**/admin/dunning-notices**', (route) => json(route, 200, list([])))

    await page.goto('/admin')

    await expect(page.locator('p.text-4xl')).toContainText('0')
    await expect(page.getByText('データがありません。')).toBeVisible()
  })
})
