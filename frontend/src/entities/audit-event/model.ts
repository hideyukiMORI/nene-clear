// Domain model for the append-only audit trail.
//
// A2 F2: the shape is the OpenAPI contract type (`api-types.ts`), not a
// hand-written mirror. Still snake_case; the camelCase model + mapper are a
// later step (fsd-a2-entities.md §4.1). What changes is *where the truth lives*:
// a spec change now breaks the build instead of silently disagreeing with the
// server.
//
// One deliberate narrowing: the spec types `action` as a plain string, while the
// UI switches on it to pick a label. `AuditAction` stays a frontend-only display
// union (#317-C); whether it becomes a registered spec enum is a #317 decision.
// Narrowing here — rather than re-declaring the whole record — keeps every other
// field tied to the contract.

import type { AuditEventDto } from './api-types'

// Append-only audit trail (terminology §2). `before` / `after` are the sanitized
// snapshots; `metadata` carries extra context keys (e.g. the targeted
// `invoice_id`) — each null when the event recorded none.
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

export type AuditEvent = Omit<AuditEventDto, 'action'> & { action: AuditAction }
