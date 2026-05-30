import { test, expect } from '@playwright/test'
import { apiRoute, json, problem, bypassLogin } from '../fixtures/helpers'

test.describe('Auth guard', () => {
  test('unauthenticated access to /admin redirects to /login', async ({ page }) => {
    await page.goto('/admin')
    await expect(page).toHaveURL(/\/login/)
  })

  test('root path redirects into the app then to login when unauthenticated', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/login/)
  })

  test('expired session (401 mid-session) clears token and bounces to login', async ({ page }) => {
    await bypassLogin(page)

    // The dashboard's first data call returns 401 → global handler redirects.
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      problem(route, 401, 'unauthorized', 'Unauthorized'),
    )
    await apiRoute(page, '**/admin/dunning-notices**', (route) =>
      json(route, 200, { items: [], total: 0, limit: 5, offset: 0 }),
    )

    await page.goto('/admin')

    // The global 401 handler clears the token and redirects; the URL is the
    // reliable signal (the harness re-injects the token on reload).
    await expect(page).toHaveURL(/\/login/)
  })
})
