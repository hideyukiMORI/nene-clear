import { test, expect } from '@playwright/test'
import type { Locator } from '@playwright/test'
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

  // #439: the sorted column's background lost on specificity and never painted.
  // Assert the painted colour, not the rule — a selector that loses is still in
  // the stylesheet, so only the computed value can tell the two apart.
  test('the sorted column header is painted differently from the unsorted ones', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-transactions?**', (route) =>
      json(route, 200, list([bankTransaction()])),
    )

    await page.goto('/admin/bank-transactions')

    // The page opens sorted by value_date, so exactly one header carries aria-sort.
    const sorted = page.locator('table.tbl thead th[aria-sort]')
    await expect(sorted).toHaveCount(1)

    const bg = (l: Locator) => l.evaluate((el) => getComputedStyle(el).backgroundColor)
    const sortedBg = await bg(sorted)
    const plainBg = await bg(page.locator('table.tbl thead th.sortable:not([aria-sort])').first())

    expect(sortedBg).not.toBe(plainBg)

    // ...and that it is the colour the rule has always asked for. Resolve the
    // token at run time rather than pasting its value, so a token change moves
    // the expectation with it.
    const navy100 = await sorted.evaluate((el) => {
      const probe = document.createElement('div')
      probe.style.backgroundColor = 'var(--navy-100)'
      el.append(probe)
      const c = getComputedStyle(probe).backgroundColor
      probe.remove()
      return c
    })
    expect(sortedBg).toBe(navy100)
  })
})
