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
