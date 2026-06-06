import { test, expect } from '@playwright/test'
import { apiRoute, json, problem, bypassLogin } from '../fixtures/helpers'

test.describe('Auth guard', () => {
  test('unauthenticated access to /admin shows the login screen in place', async ({ page }) => {
    await page.goto('/admin')
    // The auth shell renders the login form at the same URL (no redirect).
    await expect(page.locator('input[name="email"]')).toBeVisible()
    await expect(page).toHaveURL(/\/admin/)
  })

  test('root path lands in the app then shows login when unauthenticated', async ({ page }) => {
    await page.goto('/')
    await expect(page.locator('input[name="email"]')).toBeVisible()
  })

  test('expired session (401 mid-session) clears token and shows login in place', async ({ page }) => {
    await bypassLogin(page)

    // The dashboard's first data call returns 401 → global handler clears the token.
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      problem(route, 401, 'unauthorized', 'Unauthorized'),
    )
    await apiRoute(page, '**/admin/dunning-notices**', (route) =>
      json(route, 200, { items: [], total: 0, limit: 5, offset: 0 }),
    )

    await page.goto('/admin')

    // The cleared token flips the auth shell to the login form, at the same URL,
    // so re-login returns the user to this screen.
    await expect(page.locator('input[name="email"]')).toBeVisible()
    await expect(page).toHaveURL(/\/admin/)
  })
})
