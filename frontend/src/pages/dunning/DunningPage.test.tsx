import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderWithProviders } from '@tests/render'
import type { UpstreamInvoice } from '@/entities/upstream-invoice'

// One eligible (partially paid, past due) invoice with ¥2,500 outstanding.
const invoice: UpstreamInvoice = {
  invoice_id: 501,
  invoice_number: 'INV-501',
  client_id: 1,
  issued_at: '2026-03-01',
  due_at: '2026-03-31',
  total_cents: 500000,
  outstanding_cents: 250000,
  status: 'partially_paid',
  currency: 'JPY',
}

// Assertion targets → hoisted spies.
const { sendDunningNotice, testSendDunningNotice, pauseDunningNotice } = vi.hoisted(() => ({
  sendDunningNotice: vi.fn(),
  testSendDunningNotice: vi.fn(),
  pauseDunningNotice: vi.fn(),
}))

vi.mock('@/shared/api/endpoints', () => ({
  listUpstreamInvoices: () => Promise.resolve({ items: [invoice], total: 1 }),
  listDunningNotices: () => Promise.resolve({ items: [], total: 0, limit: 50, offset: 0 }),
  listDunningPauses: () => Promise.resolve({ items: [], total: 0, limit: 100, offset: 0 }),
  previewDunningNotice: () =>
    Promise.resolve({ invoice_number: 'INV-501', recipient_email: 'ap@acme.co', stage: 'initial', subject: '件名', body: '本文', template_version: 'v1' }),
  sendDunningNotice,
  testSendDunningNotice,
  pauseDunningNotice,
  resumeDunningNotice: vi.fn(),
  dunningNoticesExportPath: () => '/admin/export/dunning-notices',
  getCurrentUser: () =>
    Promise.resolve({ user_id: 1, organization_id: 7, email: 'a@b.c', role: 'admin', status: 'active', fiscal_year_end_month: null }),
}))

import DunningPage from './DunningPage'

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithProviders(
    <QueryClientProvider client={qc}>
      <DunningPage />
    </QueryClientProvider>,
  )
}

async function openModal(rowButtonName: string): Promise<HTMLElement> {
  renderPage()
  await screen.findByText('INV-501')
  fireEvent.click(screen.getByRole('button', { name: rowButtonName }))
  return screen.findByRole('dialog')
}

describe('DunningPage — eligible invoices', () => {
  beforeEach(() => {
    sendDunningNotice.mockReset().mockResolvedValue({ dunning_notice_id: 1 })
    testSendDunningNotice.mockReset().mockResolvedValue({ sent_to: 'x' })
    pauseDunningNotice.mockReset().mockResolvedValue({ dunning_pause_id: 1 })
  })

  it('lists an eligible invoice with its outstanding amount in yen', async () => {
    renderPage()
    expect(await screen.findByText('INV-501')).toBeInTheDocument()
    expect(screen.getAllByText('¥2,500').length).toBeGreaterThan(0) // 250,000 cents
  })
})

describe('DunningPage — send modal', () => {
  beforeEach(() => {
    sendDunningNotice.mockReset().mockResolvedValue({ dunning_notice_id: 1 })
    testSendDunningNotice.mockReset().mockResolvedValue({ sent_to: 'x' })
  })

  it('sends the initial-stage notice for the invoice', async () => {
    const dialog = await openModal('督促を送信')
    fireEvent.click(within(dialog).getByRole('button', { name: '督促を送信' }))

    await waitFor(() => expect(sendDunningNotice).toHaveBeenCalledTimes(1))
    expect(sendDunningNotice).toHaveBeenCalledWith(501, 'initial')
  })

  it('sends the selected stage when it is changed', async () => {
    const dialog = await openModal('督促を送信')
    fireEvent.change(within(dialog).getByRole('combobox'), { target: { value: 'final' } })
    fireEvent.click(within(dialog).getByRole('button', { name: '督促を送信' }))

    await waitFor(() => expect(sendDunningNotice).toHaveBeenCalledWith(501, 'final'))
  })

  it('gates the test-send button on a valid email address', async () => {
    const dialog = await openModal('督促を送信')
    const testBtn = within(dialog).getByRole('button', { name: 'テスト送信' })
    expect(testBtn).toBeDisabled()

    fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'ops@acme.co' } })
    expect(testBtn).toBeEnabled()

    fireEvent.click(testBtn)
    await waitFor(() => expect(testSendDunningNotice).toHaveBeenCalledWith(501, 'ops@acme.co', 'initial'))
  })
})

describe('DunningPage — pause modal', () => {
  beforeEach(() => {
    pauseDunningNotice.mockReset().mockResolvedValue({ dunning_pause_id: 1 })
  })

  it('requires a reason before pausing dunning for the invoice', async () => {
    const dialog = await openModal('停止')
    const pauseBtn = within(dialog).getByRole('button', { name: '督促を停止' })
    expect(pauseBtn).toBeDisabled()

    fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: '入金日調整中' } })
    expect(pauseBtn).toBeEnabled()

    fireEvent.click(pauseBtn)
    await waitFor(() => expect(pauseDunningNotice).toHaveBeenCalledWith(501, '入金日調整中'))
  })
})
