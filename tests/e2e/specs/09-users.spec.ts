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

  test('invite submit is disabled until an email is entered', async ({ page }) => {
    await apiRoute(page, '**/admin/users?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/users')
    await page.getByRole('button', { name: 'ユーザーを招待' }).click()

    // The modal guards an empty email by disabling submit (no inline message).
    const submit = page.getByRole('dialog').getByRole('button', { name: '招待を送信' })
    await expect(submit).toBeDisabled()

    await page.getByRole('dialog').getByRole('textbox').fill('new@acme.example')
    await expect(submit).toBeEnabled()
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
    await page.getByRole('dialog').getByRole('textbox').fill('taken@acme.example')
    await page.getByRole('dialog').getByRole('button', { name: '招待を送信' }).click()

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
    await page.getByRole('dialog').getByRole('button', { name: '削除' }).click()

    await expect(page.getByText('このユーザーを削除しますか？')).toHaveCount(0)
  })
})
