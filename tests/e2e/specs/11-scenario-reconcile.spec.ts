import { test, expect } from '@playwright/test'
import {
  apiRoute, json, list, loginViaForm, navTo, logout,
  bankTransaction, bankImportBatch, reconciliation, clearSettings,
} from '../fixtures/helpers'

/**
 * Scenario 1 — Reconciliation operator, full session.
 *
 * login → import CSV → see deposit → confirm match → it leaves "unmatched" and
 * appears in history → reverse it → it returns to unmatched → logout → blocked.
 *
 * Bug surface: React Query cache invalidation after each mutation, tab-scoped
 * queries, modal close, and that logout truly clears the session.
 */
test('scenario: import → reconcile → reverse → logout keeps state consistent', async ({ page }) => {
  // ── Stateful mock backend ─────────────────────────────────────────────
  let unmatched = [bankTransaction({ bank_transaction_id: 1, amount_cents: 110000, counterparty_text: 'カ）アクメ' })]
  let batches: ReturnType<typeof bankImportBatch>[] = []
  let history: ReturnType<typeof reconciliation>[] = []

  await apiRoute(page, '**/admin/clear-settings', (route) => json(route, 200, clearSettings()))
  await apiRoute(page, '**/admin/dunning-notices**', (route) => json(route, 200, list([])))

  // Batch list + upload (POST has no query string; list GET has one).
  await apiRoute(page, '**/admin/bank-import-batches', (route) => {
    if (route.request().method() === 'POST') {
      batches = [bankImportBatch({ source_filename: 'april.csv', row_count: 3 })]
      return json(route, 201, { bank_import_batch_id: 1, file_hash: 'h', row_count: 3 })
    }
    return json(route, 200, list(batches))
  })
  await apiRoute(page, '**/admin/bank-import-batches?**', (route) => json(route, 200, list(batches)))

  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) => json(route, 200, list(unmatched)))
  await apiRoute(page, '**/admin/bank-transactions?**', (route) => json(route, 200, list(unmatched)))
  await apiRoute(page, '**/admin/reconciliations?**', (route) => json(route, 200, list(history)))

  await apiRoute(page, '**/admin/reconciliations', (route) => {
    unmatched = [] // the deposit is now matched
    history = [reconciliation({ status: 'confirmed' })]
    return json(route, 201, reconciliation({ status: 'confirmed' }))
  })
  await apiRoute(page, '**/admin/reconciliations/*/reverse', (route) => {
    history = [reconciliation({ status: 'reversed' })]
    unmatched = [bankTransaction({ bank_transaction_id: 1, amount_cents: 110000, counterparty_text: 'カ）アクメ' })]
    return json(route, 204, null)
  })

  // ── 1. Login ──────────────────────────────────────────────────────────
  await loginViaForm(page)
  await expect(page.getByRole('heading', { name: 'ダッシュボード' })).toBeVisible()

  // ── 2. Import a bank CSV ────────────────────────────────────────────────
  await navTo(page, '銀行CSV取込', /\/admin\/bank-import/)
  await expect(page.getByText('データがありません。')).toBeVisible() // empty batch list
  await page.locator('select').selectOption('1')
  await page.locator('input[type="file"]').setInputFiles({
    name: 'april.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from('取引日,入金額\n2026/04/20,1100\n'),
  })
  await page.getByRole('button', { name: '取込む' }).click()
  // Success message + the batch list refreshes (cache invalidation).
  await expect(page.getByText('取込完了')).toBeVisible()
  await expect(page.getByText('april.csv')).toBeVisible()

  // ── 3. See the deposit on the transactions page ─────────────────────────
  await navTo(page, '銀行取引一覧', /\/admin\/bank-transactions/)
  await expect(page.getByText('カ）アクメ')).toBeVisible()
  await expect(page.getByText('¥1,100')).toBeVisible()

  // ── 4. Reconcile it ─────────────────────────────────────────────────────
  await navTo(page, '消込', /\/admin\/reconciliation/)
  await expect(page.getByText('カ）アクメ')).toBeVisible()
  await page.getByRole('button', { name: '消込を確定' }).first().click()
  const modal = page.locator('.fixed')
  await modal.locator('input[type="number"]').nth(0).fill('1')
  await modal.locator('input[type="number"]').nth(1).fill('1100')
  await modal.getByRole('button', { name: '消込を確定' }).click()
  // Modal closes and the deposit leaves the unmatched list.
  await expect(page.locator('.fixed')).toHaveCount(0)
  await expect(page.getByText('未消込の取引はありません')).toBeVisible()

  // ── 5. History shows the confirmed reconciliation ───────────────────────
  await page.getByRole('button', { name: '消込一覧' }).click()
  await expect(page.getByText('確定済')).toBeVisible()

  // ── 6. Reverse it ───────────────────────────────────────────────────────
  await page.getByRole('button', { name: '消込を取消' }).first().click()
  await page.locator('.fixed input[type="text"]').fill('誤った消込のため')
  await page.locator('.fixed').getByRole('button', { name: '消込を取消' }).click()
  await expect(page.locator('.fixed')).toHaveCount(0)
  await expect(page.getByText('取消済')).toBeVisible()

  // Back on the unmatched tab, the deposit is available again.
  await page.getByRole('button', { name: '未消込' }).click()
  await expect(page.getByText('カ）アクメ')).toBeVisible()

  // ── 7. Logout and verify the session is gone ────────────────────────────
  await logout(page)
  await page.goto('/admin')
  await expect(page).toHaveURL(/\/login/)
})
