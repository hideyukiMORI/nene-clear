import { test, expect } from '@playwright/test'
import { apiRoute, json, list, bypassLogin, bankTransaction } from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
})

test.describe('Bank transactions — list, filter, pagination boundaries', () => {
  test('empty list shows no-data message', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/bank-transactions')

    await expect(page.getByText('データがありません。')).toBeVisible()
  })

  test('renders rows with yen formatting and status badge', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions?**', (route) =>
      json(route, 200, list([bankTransaction({ amount_cents: 5000000, counterparty_text: 'カ）テスト' })])),
    )

    await page.goto('/admin/bank-transactions')

    await expect(page.getByText('¥50,000')).toBeVisible()
    await expect(page.getByText('カ）テスト')).toBeVisible()
  })

  test('pagination: first page has Back disabled, Next enabled', async ({ page }) => {
    // total 45 > PAGE_SIZE 20 → 3 pages
    const items = Array.from({ length: 20 }, (_, i) => bankTransaction({ bank_transaction_id: i + 1 }))
    await apiRoute(page, '**/admin/bank-transactions?**', (route) => json(route, 200, list(items, 45, 20, 0)))

    await page.goto('/admin/bank-transactions')

    await expect(page.getByText('1 / 3')).toBeVisible()
    await expect(page.getByRole('button', { name: '前のページ' })).toBeDisabled()
    await expect(page.getByRole('button', { name: '次のページ' })).toBeEnabled()
  })

  test('pagination: last page has Next disabled', async ({ page }) => {
    // Serve different pages based on the offset query param.
    await apiRoute(page, '**/admin/bank-transactions?**', (route) => {
      const url = new URL(route.request().url())
      const offset = Number(url.searchParams.get('offset') ?? '0')
      const items = Array.from({ length: offset >= 40 ? 5 : 20 }, (_, i) =>
        bankTransaction({ bank_transaction_id: offset + i + 1 }),
      )
      return json(route, 200, list(items, 45, 20, offset))
    })

    await page.goto('/admin/bank-transactions')
    await page.getByRole('button', { name: '次のページ' }).click() // → page 2
    await page.getByRole('button', { name: '次のページ' }).click() // → page 3 (last)

    await expect(page.getByText('3 / 3')).toBeVisible()
    await expect(page.getByRole('button', { name: '次のページ' })).toBeDisabled()
    await expect(page.getByRole('button', { name: '前のページ' })).toBeEnabled()
  })

  test('no pagination controls when total fits one page', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions?**', (route) =>
      json(route, 200, list([bankTransaction()], 1, 20, 0)),
    )

    await page.goto('/admin/bank-transactions')

    await expect(page.getByRole('button', { name: '次のページ' })).toHaveCount(0)
  })

  test('status filter triggers a scoped query', async ({ page }) => {
    let lastUrl = ''
    await apiRoute(page, '**/admin/bank-transactions?**', (route) => {
      lastUrl = route.request().url()
      return json(route, 200, list([]))
    })

    await page.goto('/admin/bank-transactions')
    await page.locator('select').selectOption('matched')
    await page.getByRole('button', { name: '検索' }).click()

    await expect.poll(() => lastUrl).toContain('status=matched')
  })
})
