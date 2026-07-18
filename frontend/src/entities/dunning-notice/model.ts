// Domain model for a dunning notice. Mirrors the API JSON exactly (snake_case;
// no renaming) — see docs/development/fsd-a2-entities.md §4.1 (transitional: the
// A1 codemod later introduces the camelCase model + mapper).

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
