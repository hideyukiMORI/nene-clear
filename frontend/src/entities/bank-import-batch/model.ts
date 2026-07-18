// Domain model for a bank-import batch. Mirrors the API JSON exactly
// (snake_case; no renaming) — see docs/development/fsd-a2-entities.md §4.1
// (transitional: the A1 codemod later introduces the camelCase model + mapper).

export interface BankImportBatch {
  bank_import_batch_id: number
  organization_id: number
  bank_account_id: number
  file_hash: string
  source_filename: string
  row_count: number
  status: 'imported' | 'reversed'
  imported_at: string
  imported_by: number
  reversed_at: string | null
  reversal_reason: string | null
}
