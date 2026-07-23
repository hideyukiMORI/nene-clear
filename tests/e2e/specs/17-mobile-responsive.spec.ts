import { test, expect, devices } from '@playwright/test'
import { apiRoute, json, list, bypassLogin } from '../fixtures/helpers'

// The reconciliation demo QR (行政書士/税理士 leaflet) lands on a phone, so the
// <=820px shell must collapse the desktop sidebar to a bottom-tab bar. iPhone 13
// (390×844) is the reference device in the design work order. Apply its viewport /
// touch emulation onto the project's chromium engine — the repo (and CI) only
// installs chromium, so we don't spread `devices['iPhone 13']` wholesale (that
// would flip defaultBrowserType to webkit, which isn't installed).
const iphone13 = devices['iPhone 13']
test.use({
  viewport: iphone13.viewport,
  userAgent: iphone13.userAgent,
  deviceScaleFactor: iphone13.deviceScaleFactor,
  isMobile: iphone13.isMobile,
  hasTouch: iphone13.hasTouch,
})

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  // Broadly stub the list endpoints so each page renders its shell without
  // falling through to the preview server. Empty is fine — these assert layout.
  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) => json(route, 200, list([])))
  await apiRoute(page, '**/admin/bank-transactions**', (route) => json(route, 200, list([])))
  await apiRoute(page, '**/admin/dunning-notices**', (route) => json(route, 200, list([])))
  await apiRoute(page, '**/admin/reconciliations**', (route) => json(route, 200, list([])))
})

test.describe('Mobile responsive shell (<=820px)', () => {
  test('sidebar collapses to a bottom-tab bar; no horizontal overflow', async ({ page }) => {
    await page.goto('/admin')

    // Desktop sidebar hidden; bottom tabs shown (the reported "サイドバー6割占拠" fix).
    await expect(page.locator('.side')).toBeHidden()
    await expect(page.locator('.mnav')).toBeVisible()

    // 4 primary tabs + Menu = 5, none wrapping off-screen.
    await expect(page.locator('.mnav .mtab')).toHaveCount(5)

    // Reconciliation (the core action) carries the unmatched-count badge.
    await expect(page.locator('.mnav a[href="/admin/reconciliation"] .mtab-badge')).toBeVisible()

    // The document itself must not scroll sideways (the reported "右端で切断").
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    )
    expect(overflow).toBeLessThanOrEqual(1)
  })

  test('Menu tab opens the sheet with the folded nav; a sheet link navigates and closes it', async ({ page }) => {
    await page.goto('/admin')

    const sheet = page.locator('.msheet')
    await expect(sheet).toBeHidden()

    await page.getByRole('button', { name: 'メニュー' }).click()
    await expect(sheet).toBeVisible()

    // The sheet folds in destinations that aren't primary tabs (e.g. 設定).
    const settings = sheet.getByRole('link', { name: '設定' })
    await expect(settings).toBeVisible()

    await settings.click()
    await page.waitForURL(/\/admin\/settings/)
    await expect(sheet).toBeHidden()
  })

  test('bottom-tab navigation switches page and marks the tab active', async ({ page }) => {
    await page.goto('/admin')

    await page.locator('.mnav a[href="/admin/dunning"]').click()
    await page.waitForURL(/\/admin\/dunning/)
    await expect(page.locator('.mnav a[href="/admin/dunning"]')).toHaveClass(/active/)
  })
})
