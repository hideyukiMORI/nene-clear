// Domain model for a client credit. Mirrors the API JSON exactly (snake_case;
// no renaming) — see docs/development/fsd-a2-entities.md §4.1 (transitional: the
// A1 codemod later introduces the camelCase model + mapper).

export interface ClientCredit {
  client_credit_id: number
  organization_id: number
  client_id: number | null
  client_name: string | null
  source: 'invoice_upstream' | 'manual'
  manual_receivable_id: number | null
  amount_cents: number
  remaining_cents: number
  status: 'open' | 'voided'
  source_bank_transaction_id: number
  reconciliation_id: number
  created_by: number
  created_at: string
}
