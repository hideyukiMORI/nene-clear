// All fields mirror the API JSON exactly (snake_case). No renaming.

export interface User {
  user_id: number
  organization_id: number | null
  email: string
  role: 'superadmin' | 'admin' | 'member' | 'viewer'
  status: 'active' | 'invited'
}

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

export interface ClientCredit {
  client_credit_id: number
  organization_id: number
  client_id: number
  amount_cents: number
  remaining_cents: number
  status: 'open' | 'partially_applied' | 'applied'
  source_bank_transaction_id: number
  reconciliation_id: number
  created_by: number
  created_at: string
}

export interface DunningNotice {
  dunning_notice_id: number
  organization_id: number
  invoice_id: number
  invoice_number: string
  client_id: number
  recipient_email: string
  outstanding_at_send_cents: number
  due_at: string
  channel: string
  sent_by: number
  sent_at: string
}

export interface ClearSettings {
  organization_id: number
  dunning_min_interval_days: number
  bank_accounts: BankAccount[]
}

export interface BankAccount {
  bank_account_id: number
  bank_name: string
  bank_branch: string
  account_type: string
  account_number: string
}

export interface ProblemDetails {
  type: string
  title: string
  status: number
  detail?: string
  errors?: Array<{ field: string; code: string; message: string }>
}

export interface ListEnvelope<T> {
  items: T[]
  limit: number
  offset: number
  total: number
}

// Invoice upstream read models (read-only from Clear's perspective)
export interface UpstreamInvoice {
  invoice_id: number
  invoice_number: string
  client_id: number
  issued_at: string
  due_at: string
  total_cents: number
  outstanding_cents: number
  status: 'issued' | 'partially_paid' | 'paid'
  currency: string
}

export interface UpstreamClient {
  client_id: number
  contact_name: string
  recipient_email: string
}
