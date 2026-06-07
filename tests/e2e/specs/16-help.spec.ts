import { test, expect } from '@playwright/test'
import { bypassLogin, navTo } from '../fixtures/helpers'

test.describe('Help page', () => {
  test('reachable from the sidebar; shows hero, ToC and an FAQ', async ({ page }) => {
    await bypassLogin(page)
    await page.goto('/admin')
    await navTo(page, 'ヘルプ', /\/admin\/help/)

    // Hero + table of contents render.
    await expect(page.getByRole('heading', { level: 1, name: '操作ガイド・よくある質問' })).toBeVisible()
    await expect(page.locator('.help-toc a').first()).toBeVisible()

    // ToC jumps to a section anchor.
    await page.locator('.help-toc a', { hasText: '督促' }).click()
    await expect(page).toHaveURL(/#dunning$/)

    // FAQ accordion: first item is open by default.
    await expect(page.locator('.faq details').first()).toHaveAttribute('open', '')
  })

  test('admin-only sections are badged in the contents', async ({ page }) => {
    await bypassLogin(page)
    await page.goto('/admin/help')
    await expect(page.locator('.help-toc a.admin-only').first()).toBeVisible()
  })

  test('sidebar disclaimer link opens the help disclaimer section', async ({ page }) => {
    await bypassLogin(page)
    await page.goto('/admin')
    await page.getByRole('link', { name: '免責事項' }).click()
    await expect(page).toHaveURL(/\/admin\/help#disclaimer$/)
    await expect(page.getByRole('heading', { level: 2, name: /免責事項/ })).toBeVisible()
  })

  test('login screen shows the warranty disclaimer', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.locator('input[name="email"]')).toBeVisible()
    await expect(page.getByText(/MIT ライセンス/)).toBeVisible()
  })
})
