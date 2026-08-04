import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, fireEvent, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderWithProviders } from '@tests/render'
import type { BankTransaction } from '@/entities/bank-transaction'

// One unmatched transaction of ¥2,500 (amounts are integer cents, yen × 100).
const unmatchedTx: BankTransaction = {
  bank_transaction_id: 99,
  organization_id: 7,
  bank_import_batch_id: 3,
  bank_account_id: 1,
  value_date: '2026-04-10',
  amount_cents: 250000,
  counterparty_text: 'カ）アクメ',
  status: 'unmatched',
}

// A ranked upstream suggestion the propose call returns for that transaction.
const suggestion = {
  source: 'invoice_upstream' as const,
  invoice_id: 501,
  invoice_number: 'INV-501',
  manual_receivable_id: null,
  reference_number: null,
  amount_cents: 250000,
  outstanding_cents: 250000,
  score: 0.92,
  reason: 'amount',
}

// confirmMatch is the assertion target, so it must be a spy reachable from both
// the hoisted mock factory and the tests → vi.hoisted (avoids the TDZ trap of
// referencing a module-level const inside a hoisted vi.mock factory).
const { confirmMatch } = vi.hoisted(() => ({ confirmMatch: vi.fn() }))

// The financial logic under test lives in the page/modal, not the transport, so
// the endpoint module is mocked (no MSW; matches the repo's manual-stub pattern).
vi.mock('@/shared/api/endpoints', () => ({
  listUnmatchedTransactions: () => Promise.resolve({ items: [unmatchedTx], total: 1, limit: 50, offset: 0 }),
  listReconciliations: () => Promise.resolve({ items: [], total: 0, limit: 50, offset: 0 }),
  proposeMatch: () => Promise.resolve({ bank_transaction_id: 99, upstream_unavailable: false, suggestions: [suggestion] }),
  confirmMatch,
  reverseReconciliation: vi.fn(),
  reconciliationsExportPath: () => '/admin/export/reconciliations',
  // useFiscalYearDefault (@/entities/user) reads /me; no fiscal month → range null.
  getCurrentUser: () =>
    Promise.resolve({ user_id: 1, organization_id: 7, email: 'a@b.c', role: 'admin', status: 'active', fiscal_year_end_month: null }),
}))

import ReconciliationPage from './ReconciliationPage'

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithProviders(
    <MemoryRouter>
      <QueryClientProvider client={qc}>
        <ReconciliationPage />
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

// Render the page, then open the confirm modal for the single unmatched row.
async function openConfirmModal(): Promise<HTMLElement> {
  renderPage()
  await screen.findByText('カ）アクメ')
  fireEvent.click(screen.getByRole('button', { name: /消込を確定/ }))
  return screen.findByRole('dialog')
}

describe('ReconciliationPage — unmatched list', () => {
  beforeEach(() => {
    confirmMatch.mockReset()
    confirmMatch.mockResolvedValue({ payment_reconciliation_id: 1 })
  })

  it('renders an unmatched transaction with its amount in yen (cents ÷ 100)', async () => {
    renderPage()
    await waitFor(() => {
      expect(screen.getByText('カ）アクメ')).toBeInTheDocument()
      // 250,000 cents → ¥2,500
      expect(screen.getAllByText('¥2,500').length).toBeGreaterThan(0)
    })
  })
})

describe('ReconciliationPage — confirm modal (allocation logic)', () => {
  beforeEach(() => {
    confirmMatch.mockReset()
    confirmMatch.mockResolvedValue({ payment_reconciliation_id: 1 })
  })

  it('blocks confirm when no allocation is entered and does not call the API', async () => {
    const dialog = await openConfirmModal()

    // Default row has invoice_id 0 / amount 0 → invalid; submitting shows the
    // localized "enter at least one target" error and never hits confirmMatch.
    fireEvent.click(within(dialog).getByRole('button', { name: /消込を確定/ }))

    await waitFor(() => {
      expect(screen.getByText(/消込先を1件以上入力してください/)).toBeInTheDocument()
    })
    expect(confirmMatch).not.toHaveBeenCalled()
  })

  it('applies a suggestion and confirms with the snake_case cents payload', async () => {
    const dialog = await openConfirmModal()

    // Take the ranked suggestion (¥2,500 outstanding) then confirm.
    fireEvent.click(await within(dialog).findByRole('button', { name: '選択' }))
    fireEvent.click(within(dialog).getByRole('button', { name: /消込を確定/ }))

    await waitFor(() => expect(confirmMatch).toHaveBeenCalledTimes(1))
    expect(confirmMatch).toHaveBeenCalledWith(
      99,
      [{ source: 'invoice_upstream', invoice_id: 501, amount_cents: 250000 }],
      undefined,
    )
  })

  it('converts a typed yen amount to integer cents (× 100) in the confirm payload', async () => {
    const dialog = await openConfirmModal()

    // Row 0 has two number inputs: invoice id, then allocation amount (in yen).
    const [invoiceInput, amountInput] = within(dialog).getAllByRole('spinbutton')
    fireEvent.change(invoiceInput, { target: { value: '777' } })
    fireEvent.change(amountInput, { target: { value: '1500' } })
    fireEvent.click(within(dialog).getByRole('button', { name: /消込を確定/ }))

    await waitFor(() => expect(confirmMatch).toHaveBeenCalledTimes(1))
    // ¥1,500 typed → 150,000 cents; invoice id parsed as a number.
    expect(confirmMatch).toHaveBeenCalledWith(
      99,
      [{ source: 'invoice_upstream', invoice_id: 777, amount_cents: 150000 }],
      undefined,
    )
  })
})
