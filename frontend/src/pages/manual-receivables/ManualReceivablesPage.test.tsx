import { describe, it, expect, vi } from 'vitest'
import { screen, waitFor, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderWithProviders } from '@/test/render'
import type { ManualReceivable } from '@/types'

const row: ManualReceivable = {
  manual_receivable_id: 1,
  organization_id: 7,
  source: 'manual',
  reference_number: 'INV-2026-001',
  client_name: 'カ）アクメ',
  recipient_email: null,
  total_cents: 11000000,
  outstanding_cents: 11000000,
  currency: 'JPY',
  issued_at: null,
  due_at: '2026-04-30',
  status: 'open',
  created_at: '2026-04-01 09:00:00',
  updated_at: '2026-04-01 09:00:00',
}

vi.mock('@/shared/api/endpoints', () => ({
  listManualReceivables: () => Promise.resolve({ items: [row], total: 1, limit: 50, offset: 0 }),
  createManualReceivable: vi.fn(),
  cancelManualReceivable: vi.fn(),
  importManualReceivables: vi.fn(),
}))

import ManualReceivablesPage from './ManualReceivablesPage'

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithProviders(
    <QueryClientProvider client={qc}>
      <ManualReceivablesPage />
    </QueryClientProvider>,
  )
}

describe('ManualReceivablesPage', () => {
  it('renders the list with the "not a tax original" framing', async () => {
    renderPage()

    // The framing copy (ADR 0014 / X1) must be present on the page.
    expect(screen.getAllByText(/適格請求書の発行・原本（写しの保存）ではありません/).length).toBeGreaterThan(0)

    await waitFor(() => {
      expect(screen.getByText('INV-2026-001')).toBeInTheDocument()
      expect(screen.getByText('カ）アクメ')).toBeInTheDocument()
      // 11,000,000 cents → ¥110,000
      expect(screen.getAllByText('¥110,000').length).toBeGreaterThan(0)
    })
  })

  it('opens the create modal showing the framing notice again', async () => {
    renderPage()
    fireEvent.click(screen.getByText('売掛を追加'))

    await waitFor(() => {
      expect(screen.getByText('INV-2026-001')).toBeDefined()
      // create modal renders its own copy of the framing notice → at least two now
      expect(screen.getAllByText(/原本（写しの保存）ではありません/).length).toBeGreaterThanOrEqual(2)
    })
  })
})
