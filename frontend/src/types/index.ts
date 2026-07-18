// All fields mirror the API JSON exactly (snake_case). No renaming.

export interface User {
  user_id: number
  organization_id: number | null
  email: string
  role: 'superadmin' | 'admin' | 'member' | 'viewer'
  status: 'active' | 'invited'
  /** Org fiscal year-end month (1–12), surfaced on /me. Null when unset. */
  fiscal_year_end_month?: number | null
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

export interface DunningNotice {
  dunning_notice_id: number
  organization_id: number
  invoice_id: number
  invoice_number: string
  client_id: number
  recipient_email: string
  outstanding_at_send_cents: number
  due_at: string | null
  channel: string
  template_version: string
  sent_by: number
  sent_at: string
}

export interface ClearSettings {
  organization_id: number
  upstream_base_url: string
  upstream_token_ref: string
  dunning_min_interval_days: number
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

// Append-only audit trail (terminology §2). `before` / `after` are the
// sanitized snapshots; `metadata` carries extra context keys (e.g. the
// targeted `invoice_id`) — each null when the event recorded none.
export type AuditAction =
  | 'bank_import'
  | 'bank_import_batch_reversed'
  | 'reconciliation_confirmed'
  | 'reconciliation_reversed'
  | 'client_credit_applied'
  | 'manual_receivable_created'
  | 'manual_receivable_updated'
  | 'manual_receivable_cancelled'
  | 'dunning_sent'
  | 'dunning_paused'
  | 'dunning_resumed'
  | 'user_created'
  | 'invitation_accepted'
  | 'user_updated'
  | 'user_deleted'
  | 'organization_created'
  | 'organization_deleted'
  | 'login_succeeded'
  | 'login_failed'
  | 'clear_settings_updated'
  | 'mfa_enabled'
  | 'mfa_disabled'

export interface AuditEvent {
  audit_event_id: number
  organization_id: number
  action: AuditAction
  // Subject record the event changed (terminology §audit entity types).
  entity_type: string
  entity_id: number | null
  actor_id: number
  occurred_at: string
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
}

// Invoice upstream read models (read-only from Clear's perspective)
export interface UpstreamInvoice {
  invoice_id: number
  invoice_number: string
  client_id: number
  issued_at: string
  due_at: string | null
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
