import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, fireEvent } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderWithProviders } from '@tests/render'
import type { ClearSettings } from '@/entities/clear-settings'

const settings: ClearSettings = {
  organization_id: 7,
  upstream_base_url: 'https://invoice.example/api',
  upstream_token_ref: 'tok-ref',
  dunning_min_interval_days: 3,
  fiscal_year_end_month: 3,
  is_dunning_schedule_enabled: true,
  dunning_initial_after_days: 3,
  dunning_reminder_after_days: 14,
  dunning_final_after_days: 30,
  dunning_window_start_hour: 9,
  dunning_window_end_hour: 18,
  is_dunning_weekdays_only: true,
  dunning_max_per_run: 50,
  bank_accounts: [
    {
      bank_account_id: 1,
      bank_name: 'みずほ',
      bank_branch: '本店',
      account_type: 'ordinary',
      account_number: '1234567',
      csv_encoding: 'utf8',
      csv_date_format: 'Y/m/d',
      csv_date_column: 0,
      csv_amount_column: 1,
      csv_counterparty_column: 3,
      csv_header_rows: 1,
    },
  ],
}

// updateClearSettings is the assertion target → hoisted spy.
const { updateClearSettings } = vi.hoisted(() => ({ updateClearSettings: vi.fn() }))

vi.mock('@/shared/api/endpoints', () => ({
  getClearSettings: () => Promise.resolve(settings),
  updateClearSettings,
  testUpstreamConnection: vi.fn(() => Promise.resolve({ ok: true })),
}))

import SettingsPage from './SettingsPage'

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithProviders(
    <QueryClientProvider client={qc}>
      <SettingsPage />
    </QueryClientProvider>,
  )
}

// Render and wait for the loaded form's save button.
async function renderForm(): Promise<HTMLElement> {
  renderPage()
  return screen.findByRole('button', { name: '変更を保存' })
}

describe('SettingsPage', () => {
  beforeEach(() => {
    updateClearSettings.mockReset()
    updateClearSettings.mockResolvedValue(settings)
  })

  it('saves the whole settings object (PUT is full-replace, #284/#314)', async () => {
    const saveBtn = await renderForm()
    fireEvent.click(saveBtn)

    await waitFor(() => expect(updateClearSettings).toHaveBeenCalledTimes(1))
    expect(updateClearSettings).toHaveBeenCalledWith({
      upstream_base_url: 'https://invoice.example/api',
      upstream_token_ref: 'tok-ref',
      dunning_min_interval_days: 3,
      fiscal_year_end_month: 3,
      bank_accounts: settings.bank_accounts,
      // This screen has no controls for the schedule yet, so it must echo back what
      // it loaded. Dropping these would reset scheduled dunning to off on every
      // unrelated save — silently, because the API would be doing exactly what a
      // full replace is supposed to do (#284). Editing controls arrive with A2 F4.
      is_dunning_schedule_enabled: true,
      dunning_initial_after_days: 3,
      dunning_reminder_after_days: 14,
      dunning_final_after_days: 30,
      dunning_window_start_hour: 9,
      dunning_window_end_hour: 18,
      is_dunning_weekdays_only: true,
      dunning_max_per_run: 50,
    })
  })

  it('blocks the save when the dunning interval is below one day', async () => {
    const saveBtn = await renderForm()

    fireEvent.change(screen.getByTestId('dunning-interval'), { target: { value: '0' } })
    fireEvent.click(saveBtn)

    await waitFor(() => {
      expect(screen.getByText('督促間隔は1日以上で入力してください。')).toBeInTheDocument()
    })
    expect(updateClearSettings).not.toHaveBeenCalled()
  })

  it('sends a null fiscal-year-end month when it is cleared', async () => {
    const saveBtn = await renderForm()

    fireEvent.change(screen.getByTestId('fiscal-year-end-month'), { target: { value: '' } })
    fireEvent.click(saveBtn)

    await waitFor(() => expect(updateClearSettings).toHaveBeenCalledTimes(1))
    expect(updateClearSettings.mock.calls[0][0]).toMatchObject({ fiscal_year_end_month: null })
  })

  it('masks the bank account number until it is explicitly revealed (#192)', async () => {
    await renderForm()

    const numberInput = screen.getByDisplayValue('1234567')
    expect(numberInput).toHaveAttribute('type', 'password')

    fireEvent.click(screen.getByRole('button', { name: '表示' }))
    expect(screen.getByDisplayValue('1234567')).toHaveAttribute('type', 'text')
  })
})
