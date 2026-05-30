import { test, expect } from '@playwright/test'
import { apiRoute, json, problem, list, bypassLogin, dunningNotice } from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  await apiRoute(page, '**/admin/dunning-notices?**', (route) => json(route, 200, list([])))
})

test.describe('Dunning — send boundaries', () => {
  test('empty / zero invoice id shows client-side validation error', async ({ page }) => {
    let posted = false
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        posted = true
        return json(route, 201, dunningNotice())
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByText('有効な請求書IDを入力してください')).toBeVisible()
    expect(posted).toBe(false)
  })

  test('invoice not eligible (422) surfaces the upstream error', async ({ page }) => {
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        return problem(route, 422, 'invoice-not-eligible-for-dunning', 'この請求書は支払済み、または残高がないため督促できません。')
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.locator('input[type="number"]').fill('123')
    await page.getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByText('この請求書は支払済み、または残高がないため督促できません。')).toBeVisible()
  })

  test('too frequent (422) surfaces the interval error', async ({ page }) => {
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        return problem(route, 422, 'dunning-too-frequent', '前回の督促から最短間隔が経過していません。', undefined, {
          next_allowed_at: '2026-05-01 09:00:00',
        })
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.locator('input[type="number"]').fill('123')
    await page.getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByText('前回の督促から最短間隔が経過していません。')).toBeVisible()
  })

  test('successful send shows the confirmation message', async ({ page }) => {
    await apiRoute(page, '**/admin/dunning-notices', (route) => {
      if (route.request().method() === 'POST') {
        return json(route, 201, dunningNotice())
      }
      return json(route, 200, list([]))
    })

    await page.goto('/admin/dunning')
    await page.locator('input[type="number"]').fill('123')
    await page.getByRole('button', { name: '督促を送信' }).click()

    await expect(page.getByText('督促メールを送信しました。')).toBeVisible()
  })
})
