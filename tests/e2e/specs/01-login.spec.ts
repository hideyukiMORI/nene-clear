import { test, expect } from '@playwright/test'
import { apiRoute, json, problem, TOKEN_KEY } from '../fixtures/helpers'

test.describe('Login', () => {
  test('blocks submit when fields are empty (zod validation)', async ({ page }) => {
    let loginCalled = false
    await apiRoute(page, '**/admin/auth/login', (route) => {
      loginCalled = true
      return json(route, 200, { token: 'x', user: {} })
    })

    await page.goto('/login')
    await page.getByRole('button').click()

    // zod min(1) on email + password prevents the API call.
    await page.waitForTimeout(300)
    expect(loginCalled).toBe(false)
    await expect(page).toHaveURL(/\/login/)
  })

  test('shows error detail on wrong credentials (401) and stays on login', async ({ page }) => {
    await apiRoute(page, '**/admin/auth/login', (route) =>
      problem(route, 401, 'invalid-credentials', '認証失敗', 'メールアドレスまたはパスワードが正しくありません。'),
    )

    await page.goto('/login')
    await page.locator('input[name="email"]').fill('admin@nene-clear.dev')
    await page.locator('input[name="password"]').fill('wrong-pass')
    await page.getByRole('button').click()

    await expect(
      page.getByText('メールアドレスまたはパスワードが正しくありません。'),
    ).toBeVisible()
    await expect(page).toHaveURL(/\/login/)
    // Token must not have been stored.
    const token = await page.evaluate((k) => sessionStorage.getItem(k), TOKEN_KEY)
    expect(token).toBeNull()
  })

  test('stores token and redirects to /admin on success', async ({ page }) => {
    await apiRoute(page, '**/admin/auth/login', (route) =>
      json(route, 200, {
        token: 'jwt-success',
        user: { user_id: 1, email: 'admin@nene-clear.dev', role: 'superadmin', organization_id: null },
      }),
    )
    // Dashboard data after redirect.
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      json(route, 200, { items: [], total: 0, limit: 1, offset: 0 }),
    )
    await apiRoute(page, '**/admin/dunning-notices**', (route) =>
      json(route, 200, { items: [], total: 0, limit: 5, offset: 0 }),
    )

    await page.goto('/login')
    await page.locator('input[name="email"]').fill('admin@nene-clear.dev')
    await page.locator('input[name="password"]').fill('admin1234')
    await page.getByRole('button').click()

    await expect(page).toHaveURL(/\/admin$/)
    const token = await page.evaluate((k) => sessionStorage.getItem(k), TOKEN_KEY)
    expect(token).toBe('jwt-success')
  })
})
