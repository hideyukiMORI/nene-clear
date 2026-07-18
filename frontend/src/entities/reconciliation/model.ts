// Domain model for a payment reconciliation and its allocations. Mirrors the
// API JSON exactly (snake_case; no renaming) — see
// docs/development/fsd-a2-entities.md §4.1 (transitional: the A1 codemod later
// introduces the camelCase model + mapper).

export interface ReconciliationAllocation {
  reconciliation_allocation_id: number
  organization_id: number
  payment_reconciliation_id: number
  invoice_id: number
  amount_cents: number
  payment_id: number | null
  external_reference: string | null
}

export interface Reconciliation {
  payment_reconciliation_id: number
  organization_id: number
  bank_transaction_id: number
  status: 'confirmed' | 'reversed'
  reason_code: string | null
  confirmed_by: number
  confirmed_at: string
  reversed_at: string | null
  reversal_reason: string | null
  allocations: ReconciliationAllocation[]
}
