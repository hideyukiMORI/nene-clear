import { test, expect } from '@playwright/test'
import { apiRoute, json, list, bypassLogin, mockMe } from '../fixtures/helpers'

test.describe('Fiscal-year default date range', () => {
  test('date filter defaults to the current fiscal year derived from 決算月', async ({ page }) => {
    await bypassLogin(page)
    // March year-end → fiscal year starts April 1.
    await mockMe(page, { fiscal_year_end_month: 3 })
    await apiRoute(page, '**/admin/bank-transactions?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/bank-transactions')

    // The value-date "from" input is pre-filled with this fiscal year's April 1.
    await expect(page.locator('input[type="date"]').first()).toHaveValue(/^\d{4}-04-01$/)
  })

  test('no default when 決算月 is unset (export/filter cover all)', async ({ page }) => {
    await bypassLogin(page)
    await mockMe(page, { fiscal_year_end_month: null })
    await apiRoute(page, '**/admin/bank-transactions?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/bank-transactions')

    await expect(page.locator('input[type="date"]').first()).toHaveValue('')
  })
})
