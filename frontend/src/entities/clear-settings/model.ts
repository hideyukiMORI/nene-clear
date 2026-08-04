// Domain models for the org's Clear settings and its bank accounts. Mirror the
// API JSON exactly (snake_case; no renaming) — see
// docs/development/fsd-a2-entities.md §4.1 (transitional: the A1 codemod later
// introduces the camelCase model + mapper). `BankAccount` is co-located here
// (not a sibling slice) because it is only ever used within settings + the CSV
// column mapping, which keeps ClearSettings free of a sibling-entity import
// (design §6.1, hub Q2).

export interface ClearSettings {
  organization_id: number
  upstream_base_url: string
  upstream_token_ref: string
  dunning_min_interval_days: number
  // Scheduled dunning (#400 §6). No UI edits these yet — the settings screen
  // echoes them back untouched on save, because PUT /admin/clear-settings is a
  // FULL REPLACE (#284): a field left out of the body is reset to its default,
  // not preserved. Editing controls arrive with the A2 F4 settings rework.
  is_dunning_schedule_enabled: boolean
  dunning_initial_after_days: number
  dunning_reminder_after_days: number
  dunning_final_after_days: number
  dunning_window_start_hour: number
  dunning_window_end_hour: number
  is_dunning_weekdays_only: boolean
  dunning_max_per_run: number
  fiscal_year_end_month: number | null
  bank_accounts: BankAccount[]
}

export interface BankAccount {
  bank_account_id?: number
  bank_name: string
  bank_branch: string
  account_type: 'ordinary' | 'current'
  account_number: string
  csv_encoding?: string
  csv_date_format?: string
  csv_date_column?: number
  csv_amount_column?: number
  csv_counterparty_column?: number
  csv_header_rows?: number
}
