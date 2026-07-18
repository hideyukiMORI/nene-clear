// Invoice upstream read models (read-only from Clear's perspective). Mirror the
// API JSON exactly (snake_case; no renaming) — see
// docs/development/fsd-a2-entities.md §4.1 (transitional: the A1 codemod later
// introduces the camelCase model + mapper).
//
// `UpstreamClient` is co-located here (both are the upstream read models grouped
// under one comment in the pre-A2 types file) rather than a standalone
// `upstream-client` slice: it currently has no importer, so a standalone slice
// would be a knip-flagged dead file. See design §4 deviation note + hub Q.

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
