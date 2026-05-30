import { test, expect } from '@playwright/test'
import { apiRoute, json, problem, list, bypassLogin, user } from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
})

test.describe('Users — invite + delete boundaries', () => {
  test('empty list shows no-data', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/users')

    await expect(page.getByText('データがありません。')).toBeVisible()
  })

  test('renders role and status badges', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) =>
      json(route, 200, list([user({ role: 'admin', status: 'invited', email: 'a@acme.example' })])),
    )

    await page.goto('/admin/users')

    await expect(page.getByText('a@acme.example')).toBeVisible()
    await expect(page.getByText('招待中')).toBeVisible()
  })

  test('invite with empty email shows client-side error', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/users')
    await page.getByRole('button', { name: 'ユーザーを招待' }).click()
    // Submit the modal form without an email.
    await page.locator('.fixed').getByRole('button', { name: 'ユーザーを招待' }).click()

    await expect(page.getByText('メールアドレスを入力してください')).toBeVisible()
  })

  test('invite duplicate email (409) surfaces the upstream error', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) => json(route, 200, list([])))
    await apiRoute(page, '**/admin/users', (route) => {
      if (route.request().method() === 'POST') {
        return problem(route, 409, 'user-already-exists', 'そのメールアドレスはすでに使用されています。')
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/users')
    await page.getByRole('button', { name: 'ユーザーを招待' }).click()
    await page.locator('.fixed input[type="email"]').fill('taken@acme.example')
    await page.locator('.fixed').getByRole('button', { name: 'ユーザーを招待' }).click()

    await expect(page.getByText('そのメールアドレスはすでに使用されています。')).toBeVisible()
  })

  test('delete confirmation then success removes the dialog', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) =>
      json(route, 200, list([user({ email: 'gone@acme.example' })])),
    )
    await apiRoute(page, '**/admin/users/*', (route) => json(route, 204, null))

    await page.goto('/admin/users')
    await page.getByRole('button', { name: '削除' }).first().click()

    await expect(page.getByText('このユーザーを削除しますか？')).toBeVisible()
    await page.locator('.fixed').getByRole('button', { name: '削除' }).click()

    await expect(page.getByText('このユーザーを削除しますか？')).toHaveCount(0)
  })
})
