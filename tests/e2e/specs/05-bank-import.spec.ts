import { test, expect } from '@playwright/test'
import {
  apiRoute, json, problem, list, bypassLogin, bankImportBatch, clearSettings,
} from '../fixtures/helpers'

test.beforeEach(async ({ page }) => {
  await bypassLogin(page)
  await apiRoute(page, '**/admin/clear-settings', (route) => json(route, 200, clearSettings()))
})

test.describe('Bank import — upload + reverse boundaries', () => {
  test('upload without file/account shows a validation error', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-import-batches**', (route) => json(route, 200, list([])))

    await page.goto('/admin/bank-import')
    await page.getByRole('button', { name: '取込む' }).click()

    await expect(page.getByText('銀行口座とファイルを選択してください')).toBeVisible()
  })

  test('duplicate import (409) surfaces the upstream error', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-import-batches', (route) => {
      if (route.request().method() === 'POST') {
        return problem(route, 409, 'duplicate-bank-import', 'インポート重複', 'このファイルはすでにインポートされています。')
      }
      return json(route, 200, list([]))
    })
    // The batch list GET (with query string or trailing) — separate glob.
    await apiRoute(page, '**/admin/bank-import-batches?**', (route) => json(route, 200, list([])))

    await page.goto('/admin/bank-import')
    await page.locator('select').selectOption('1')
    await page.locator('input[type="file"]').setInputFiles({
      name: 'dup.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from('取引日,入金額\n2026/04/20,1000\n'),
    })
    await page.getByRole('button', { name: '取込む' }).click()

    // importBankCsv uses raw fetch and surfaces the JSON body's title via err.message;
    // the page shows whatever the rejected error carries. The 409 body is rendered.
    await expect(page.getByText(/インポート重複|すでにインポート/)).toBeVisible()
  })

  test('reverse dialog: button disabled until a reason is entered', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-import-batches**', (route) =>
      json(route, 200, list([bankImportBatch({ status: 'imported' })])),
    )

    await page.goto('/admin/bank-import')
    await page.getByRole('button', { name: '取消' }).first().click()

    const dialog = page.getByRole('dialog')
    const confirmBtn = dialog.getByRole('button', { name: '取消' })
    await expect(confirmBtn).toBeDisabled()

    await dialog.getByRole('textbox').fill('誤った取込のため')
    await expect(confirmBtn).toBeEnabled()
  })

  test('reverse succeeds and closes the dialog', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-import-batches', (route) => json(route, 200, list([bankImportBatch()])))
    await apiRoute(page, '**/admin/bank-import-batches?**', (route) => json(route, 200, list([bankImportBatch()])))
    await apiRoute(page, '**/admin/bank-import-batches/*/reverse', (route) => json(route, 204, null))

    await page.goto('/admin/bank-import')
    await page.getByRole('button', { name: '取消' }).first().click()
    await page.getByRole('dialog').getByRole('textbox').fill('誤った取込のため')
    await page.getByRole('dialog').getByRole('button', { name: '取消' }).click()

    // Dialog closes (reversal reason input disappears).
    await expect(page.getByRole('dialog')).toHaveCount(0)
  })

  test('reversed batch row shows no reverse button', async ({ page }) => {
    await apiRoute(page, '**/admin/bank-import-batches**', (route) =>
      json(route, 200, list([bankImportBatch({ status: 'reversed' })])),
    )

    await page.goto('/admin/bank-import')

    await expect(page.getByText('取消済')).toBeVisible()
    await expect(page.getByRole('button', { name: '取消' })).toHaveCount(0)
  })
})
