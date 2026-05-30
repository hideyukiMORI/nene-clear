import { test, expect } from '@playwright/test'
import {
  apiRoute, json, problem, list, bypassLogin, bankTransaction, reconciliation,
} from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  // The page loads on the "unmatched" tab and always fires this query. Default
  // it to empty so history-tab tests don't fall through to the preview server.
  // Tests that need rows register their own (later → higher priority) route.
  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
    json(route, 200, list([])),
  )
})

test.describe('Reconciliation — confirm + reverse boundaries', () => {
  test('confirm with allocation exceeding outstanding shows 422 error', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      json(route, 200, list([bankTransaction({ amount_cents: 110000 })])),
    )
    await apiRoute(page, '**/admin/reconciliations', (route) =>
      problem(route, 422, 'allocation-exceeds-outstanding', '消込金額が請求書の未収残高を超えています', undefined, {
        invoice_id: 1,
        outstanding_cents: 50000,
      }),
    )

    await page.goto('/admin/reconciliation')
    await page.getByRole('button', { name: '消込を確定' }).first().click()

    // Fill the allocation (invoice id + amount in yen).
    const modal = page.locator('.fixed')
    await modal.locator('input[type="number"]').nth(0).fill('1')
    await modal.locator('input[type="number"]').nth(1).fill('1100')
    await modal.getByRole('button', { name: '消込を確定' }).click()

    await expect(page.getByText('消込金額が請求書の未収残高を超えています')).toBeVisible()
  })

  test('confirm succeeds and closes the modal', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
      json(route, 200, list([bankTransaction()])),
    )
    await apiRoute(page, '**/admin/reconciliations', (route) =>
      json(route, 201, reconciliation({ allocations: [] })),
    )

    await page.goto('/admin/reconciliation')
    await page.getByRole('button', { name: '消込を確定' }).first().click()
    const modal = page.locator('.fixed')
    await modal.locator('input[type="number"]').nth(0).fill('1')
    await modal.locator('input[type="number"]').nth(1).fill('1100')
    await modal.getByRole('button', { name: '消込を確定' }).click()

    await expect(page.locator('.fixed')).toHaveCount(0)
  })

  test('history: reverse button disabled until reason entered, then succeeds', async ({ page }) => {
    await apiRoute(page, '**/admin/reconciliations?**', (route) =>
      json(route, 200, list([reconciliation({ status: 'confirmed' })])),
    )
    await apiRoute(page, '**/admin/reconciliations/*/reverse', (route) => json(route, 204, null))

    await page.goto('/admin/reconciliation')
    await page.getByRole('button', { name: '消込一覧' }).click()
    await page.getByRole('button', { name: '消込を取消' }).first().click()

    const confirmBtn = page.locator('.fixed').getByRole('button', { name: '消込を取消' })
    await expect(confirmBtn).toBeDisabled()
    await page.locator('.fixed input[type="text"]').fill('オペレーター誤操作')
    await expect(confirmBtn).toBeEnabled()
    await confirmBtn.click()

    await expect(page.locator('.fixed')).toHaveCount(0)
  })

  test('reverse of an already-reversed match (409) shows the conflict', async ({ page }) => {
    await apiRoute(page, '**/admin/reconciliations?**', (route) =>
      json(route, 200, list([reconciliation({ status: 'confirmed' })])),
    )
    await apiRoute(page, '**/admin/reconciliations/*/reverse', (route) =>
      problem(route, 409, 'invalid-state-transition', 'この消込はすでに取り消されています。'),
    )

    await page.goto('/admin/reconciliation')
    await page.getByRole('button', { name: '消込一覧' }).click()
    await page.getByRole('button', { name: '消込を取消' }).first().click()
    await page.locator('.fixed input[type="text"]').fill('二重取消')
    await page.locator('.fixed').getByRole('button', { name: '消込を取消' }).click()

    await expect(page.getByText('この消込はすでに取り消されています。')).toBeVisible()
  })

  test('reversed rows expose no reverse button', async ({ page }) => {
    await apiRoute(page, '**/admin/reconciliations?**', (route) =>
      json(route, 200, list([reconciliation({ status: 'reversed' })])),
    )

    await page.goto('/admin/reconciliation')
    await page.getByRole('button', { name: '消込一覧' }).click()

    await expect(page.getByText('取消済')).toBeVisible()
    await expect(page.getByRole('button', { name: '消込を取消' })).toHaveCount(0)
  })
})
