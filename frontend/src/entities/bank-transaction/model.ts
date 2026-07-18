// Domain model for a bank transaction. Mirrors the API JSON exactly
// (snake_case; no renaming) — see docs/development/fsd-a2-entities.md §4.1
// (transitional: the A1 codemod later introduces the camelCase model + mapper).

export interface BankTransaction {
  bank_transaction_id: number
  organization_id: number
  bank_import_batch_id: number
  bank_account_id: number
  value_date: string
  amount_cents: number
  counterparty_text: string
  status: 'unmatched' | 'partially_matched' | 'matched' | 'voided'
}
