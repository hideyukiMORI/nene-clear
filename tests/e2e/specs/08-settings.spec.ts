import { test, expect } from '@playwright/test'
import { apiRoute, json, bypassLogin, clearSettings } from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  await apiRoute(page, '**/admin/clear-settings', (route) => {
    if (route.request().method() === 'PUT') {
      return json(route, 200, clearSettings({ dunning_min_interval_days: 14 }))
    }
    return json(route, 200, clearSettings({ dunning_min_interval_days: 7 }))
  })
})

test.describe('Settings — dunning interval + connection test', () => {
  test('loads current settings and bank accounts', async ({ page }) => {
    await page.goto('/admin/settings')

    await expect(page.getByTestId('dunning-interval')).toHaveValue('7')
    // The bank name is now an editable field, so assert its value rather than text.
    await expect(page.locator('.acct-card input').first()).toHaveValue('テスト銀行')
  })

  test('save shows the saved confirmation', async ({ page }) => {
    await page.goto('/admin/settings')
    await page.getByTestId('dunning-interval').fill('14')
    await page.getByRole('button', { name: '保存' }).click()

    await expect(page.getByText('保存しました')).toBeVisible()
  })

  test('interval below 1 is rejected by client validation', async ({ page }) => {
    let putCalled = false
    await page.route('**/admin/clear-settings', async (route) => {
      if (route.request().method() === 'PUT' && route.request().resourceType() !== 'document') {
        putCalled = true
      }
      return route.fallback()
    })

    await page.goto('/admin/settings')
    await page.getByTestId('dunning-interval').fill('0')
    await page.getByRole('button', { name: '保存' }).click()

    // The client guard (min 1) blocks the request and shows a field error.
    await page.waitForTimeout(300)
    expect(putCalled).toBe(false)
  })

  test('test connection shows success', async ({ page }) => {
    await apiRoute(page, '**/admin/clear-settings/test-upstream', (route) =>
      json(route, 200, { ok: true }),
    )

    await page.goto('/admin/settings')
    await page.getByRole('button', { name: '接続テスト' }).click()

    await expect(page.getByText('接続成功')).toBeVisible()
  })

  test('test connection shows failure', async ({ page }) => {
    await apiRoute(page, '**/admin/clear-settings/test-upstream', (route) =>
      json(route, 200, { ok: false }),
    )

    await page.goto('/admin/settings')
    await page.getByRole('button', { name: '接続テスト' }).click()

    await expect(page.getByText('接続失敗')).toBeVisible()
  })
})
