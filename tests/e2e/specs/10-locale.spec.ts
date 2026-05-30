import { test, expect } from '@playwright/test'
import { apiRoute, json, list, bypassLogin } from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
    json(route, 200, { items: [], total: 0, limit: 1, offset: 0 }),
  )
  await apiRoute(page, '**/admin/dunning-notices**', (route) => json(route, 200, list([])))
})

test.describe('Locale switching (ADR 0005)', () => {
  test('defaults to Japanese and switches to English', async ({ page }) => {
    await page.goto('/admin')

    // Japanese default — sidebar nav.
    await expect(page.getByRole('link', { name: 'ダッシュボード' })).toBeVisible()

    await page.getByRole('button', { name: 'EN' }).click()

    await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'ダッシュボード' })).toHaveCount(0)
  })

  test('locale choice persists across navigation', async ({ page }) => {
    await page.goto('/admin')
    await page.getByRole('button', { name: 'EN' }).click()
    await page.getByRole('link', { name: 'Settings' }).click()

    await expect(page).toHaveURL(/\/admin\/settings/)
    // html lang attribute reflects the chosen locale.
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  })
})
