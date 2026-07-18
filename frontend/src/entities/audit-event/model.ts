// Domain model for the append-only audit trail. Mirrors the API JSON exactly
// (snake_case; no renaming) — see docs/development/fsd-a2-entities.md §4.1
// (transitional: the A1 codemod later introduces the camelCase model + mapper).
// `AuditAction` stays a frontend-only display union (design §5, #317-C); whether
// it becomes a registered spec enum is a #317 decision.

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
