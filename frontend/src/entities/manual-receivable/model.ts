// Domain models for manual receivables. Mirror the API JSON exactly (snake_case;
// no renaming) — see docs/development/fsd-a2-entities.md §4.1 (transitional: the
// A1 codemod later introduces the camelCase model + mapper).

/**
 * A receivable entered directly in Clear, not sourced from NeNe Invoice
 * (ADR 0014). A reconciliation reference — NOT an issued invoice / 適格請求書 /
 * tax original. `*_cents` are integer cents (yen × 100), like everywhere else.
 */
export interface ManualReceivable {
  manual_receivable_id: number
  organization_id: number
  source: 'manual'
  reference_number: string
  client_name: string
  recipient_email: string | null
  total_cents: number
  outstanding_cents: number
  currency: string
  issued_at: string | null
  due_at: string | null
  status: 'open' | 'partially_paid' | 'paid' | 'cancelled'
  created_at: string
  updated_at: string
}

export interface ManualReceivableImportResult {
  created: number
  skipped: number
  errors: { row: number; errors: { field: string; message: string }[] }[]
}
