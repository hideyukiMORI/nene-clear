import { api } from './client'

export async function downloadCsv(path: string, filename: string): Promise<void> {
  const token = sessionStorage.getItem('nene_clear_token')
  const res = await fetch(path, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!res.ok) throw new Error(`Export failed: ${res.status}`)
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
import type {
  User, BankImportBatch, BankTransaction, Reconciliation,
  ClientCredit, DunningNotice, ClearSettings, UpstreamInvoice,
  AuditEvent, ListEnvelope,
} from '@/types'

const BASE = '/admin'

// --- Auth ---
export function login(email: string, password: string) {
  return api.post<{ token: string }>(`${BASE}/auth/login`, { email, password })
}

export function getCurrentUser(signal?: AbortSignal) {
  return api.get<User>(`${BASE}/auth/me`, signal)
}

// --- Bank import ---
export interface BankImportBatchQuery {
  sourceFilename?: string
  status?: string
  rowCountMin?: number
  rowCountMax?: number
  importedFrom?: string
  importedTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listBankImportBatches(params: BankImportBatchQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.sourceFilename) q.set('source_filename', params.sourceFilename)
  if (params.status) q.set('status', params.status)
  if (params.rowCountMin != null) q.set('row_count_min', String(params.rowCountMin))
  if (params.rowCountMax != null) q.set('row_count_max', String(params.rowCountMax))
  if (params.importedFrom) q.set('imported_from', params.importedFrom)
  if (params.importedTo) q.set('imported_to', params.importedTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<BankImportBatch>>(`${BASE}/bank-import-batches?${q}`, signal)
}

export async function importBankCsv(bankAccountId: number, file: File) {
  const form = new FormData()
  form.append('bank_account_id', String(bankAccountId))
  form.append('file', file)
  const token = sessionStorage.getItem('nene_clear_token')
  const res = await fetch(`${BASE}/bank-import-batches`, {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    body: form,
  })

  const data = await res.json().catch(() => null)

  // Multipart upload bypasses the JSON api client, so status handling lives here:
  // a non-2xx response must surface as an error, not be treated as a successful import.
  if (!res.ok) {
    const detail =
      data && typeof data === 'object' && 'detail' in data
        ? String((data as { detail?: unknown }).detail ?? '')
        : ''
    const title =
      data && typeof data === 'object' && 'title' in data
        ? String((data as { title?: unknown }).title ?? '')
        : `Import failed (${res.status})`
    throw new Error(detail || title)
  }

  return data
}

export function reverseBankImportBatch(batchId: number, reversalReason: string) {
  return api.post<void>(`${BASE}/bank-import-batches/${batchId}/reverse`, { reversal_reason: reversalReason })
}

// --- Bank transactions ---
export interface BankTransactionFilter {
  status?: string
  value_date_from?: string
  value_date_to?: string
  amount_min_cents?: number
  amount_max_cents?: number
  counterparty?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listBankTransactions(filter: BankTransactionFilter, signal?: AbortSignal) {
  const q = new URLSearchParams()
  if (filter.status) q.set('status', filter.status)
  if (filter.value_date_from) q.set('value_date_from', filter.value_date_from)
  if (filter.value_date_to) q.set('value_date_to', filter.value_date_to)
  if (filter.amount_min_cents != null) q.set('amount_min_cents', String(filter.amount_min_cents))
  if (filter.amount_max_cents != null) q.set('amount_max_cents', String(filter.amount_max_cents))
  if (filter.counterparty) q.set('counterparty', filter.counterparty)
  if (filter.sortBy) q.set('sort_by', filter.sortBy)
  if (filter.sortDir) q.set('sort_dir', filter.sortDir)
  q.set('limit', String(filter.limit ?? 50))
  q.set('offset', String(filter.offset ?? 0))
  return api.get<ListEnvelope<BankTransaction>>(`${BASE}/bank-transactions?${q}`, signal)
}

export function listUnmatchedTransactions(params: { limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  return api.get<ListEnvelope<BankTransaction>>(`${BASE}/bank-transactions/unmatched?${q}`, signal)
}

// --- Reconciliation ---
export interface ReconciliationQuery {
  status?: string
  bankTransactionId?: string
  invoiceId?: string
  confirmedFrom?: string
  confirmedTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listReconciliations(params: ReconciliationQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.status) q.set('status', params.status)
  if (params.bankTransactionId) q.set('bank_transaction_id', params.bankTransactionId)
  if (params.invoiceId) q.set('invoice_id', params.invoiceId)
  if (params.confirmedFrom) q.set('confirmed_from', params.confirmedFrom)
  if (params.confirmedTo) q.set('confirmed_to', params.confirmedTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<Reconciliation>>(`${BASE}/reconciliations?${q}`, signal)
}

export function getReconciliation(id: number, signal?: AbortSignal) {
  return api.get<Reconciliation>(`${BASE}/reconciliations/${id}`, signal)
}

export interface AllocationInput { invoice_id: number; amount_cents: number }

export function proposeMatch(bankTransactionId: number) {
  return api.post<{ invoices: UpstreamInvoice[] }>(`${BASE}/reconciliations/propose`, { bank_transaction_id: bankTransactionId })
}

export function confirmMatch(
  bankTransactionId: number,
  allocations: AllocationInput[],
  reasonCode?: string,
  _idempotencyKey?: string,
) {
  // Backend route is POST /admin/reconciliations (see ReconciliationRouteRegistrar);
  // there is no /confirm sub-path.
  return api.post<Reconciliation>(
    `${BASE}/reconciliations`,
    { bank_transaction_id: bankTransactionId, allocations, reason_code: reasonCode },
  )
}

export function reverseReconciliation(id: number, reversalReason: string) {
  return api.post<void>(`${BASE}/reconciliations/${id}/reverse`, { reversal_reason: reversalReason })
}

// --- Client credits ---
export interface ClientCreditQuery {
  clientId?: string
  status?: string
  amountMinCents?: number
  amountMaxCents?: number
  remainingMinCents?: number
  remainingMaxCents?: number
  createdFrom?: string
  createdTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listClientCredits(params: ClientCreditQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.clientId) q.set('client_id', params.clientId)
  if (params.status) q.set('status', params.status)
  if (params.amountMinCents != null) q.set('amount_min_cents', String(params.amountMinCents))
  if (params.amountMaxCents != null) q.set('amount_max_cents', String(params.amountMaxCents))
  if (params.remainingMinCents != null) q.set('remaining_min_cents', String(params.remainingMinCents))
  if (params.remainingMaxCents != null) q.set('remaining_max_cents', String(params.remainingMaxCents))
  if (params.createdFrom) q.set('created_from', params.createdFrom)
  if (params.createdTo) q.set('created_to', params.createdTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<ClientCredit>>(`${BASE}/client-credits?${q}`, signal)
}

export function applyClientCredit(creditId: number, invoiceId: number, amountCents: number) {
  return api.post<ClientCredit>(`${BASE}/client-credits/${creditId}/apply`, {
    invoice_id: invoiceId,
    amount_cents: amountCents,
  })
}

// --- Upstream invoices ---
export function listUpstreamInvoices(params: { status?: string }, signal?: AbortSignal) {
  const q = new URLSearchParams()
  if (params.status) q.set('status', params.status)
  return api.get<{ items: UpstreamInvoice[]; total: number }>(`${BASE}/upstream/invoices?${q}`, signal)
}

// --- Dunning ---
export interface DunningNoticeQuery {
  invoiceNumber?: string
  recipientEmail?: string
  outstandingMinCents?: number
  outstandingMaxCents?: number
  sentFrom?: string
  sentTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listDunningNotices(params: DunningNoticeQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.invoiceNumber) q.set('invoice_number', params.invoiceNumber)
  if (params.recipientEmail) q.set('recipient_email', params.recipientEmail)
  if (params.outstandingMinCents != null) q.set('outstanding_min_cents', String(params.outstandingMinCents))
  if (params.outstandingMaxCents != null) q.set('outstanding_max_cents', String(params.outstandingMaxCents))
  if (params.sentFrom) q.set('sent_from', params.sentFrom)
  if (params.sentTo) q.set('sent_to', params.sentTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<DunningNotice>>(`${BASE}/dunning-notices?${q}`, signal)
}

// --- Audit trail (admin) ---
export interface AuditQuery {
  eventType?: string
  actorUserId?: string
  occurredFrom?: string
  occurredTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listAuditEvents(params: AuditQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.eventType) q.set('event_type', params.eventType)
  if (params.actorUserId) q.set('actor_user_id', params.actorUserId)
  if (params.occurredFrom) q.set('occurred_from', params.occurredFrom)
  if (params.occurredTo) q.set('occurred_to', params.occurredTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<AuditEvent>>(`${BASE}/audit-events?${q}`, signal)
}

export function sendDunningNotice(invoiceId: number) {
  return api.post<DunningNotice>(`${BASE}/dunning-notices`, { invoice_id: invoiceId })
}

export interface DunningPause {
  dunning_pause_id: number | null
  invoice_id: number
  paused_by: number
  paused_at: string
  paused_reason: string
  unpaused_by: number | null
  unpaused_at: string | null
  is_active: boolean
}

export function listDunningPauses(params: { active_only?: boolean; limit?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 100) })
  if (params.active_only) q.set('active_only', 'true')
  return api.get<ListEnvelope<DunningPause>>(`${BASE}/dunning-pauses?${q}`, signal)
}

export function pauseDunningNotice(invoiceId: number, reason: string) {
  return api.post<DunningPause>(`${BASE}/dunning-pauses`, { invoice_id: invoiceId, reason })
}

export function resumeDunningNotice(invoiceId: number) {
  return api.post<void>(`${BASE}/dunning-pauses/${invoiceId}/resume`)
}

// --- Settings ---
export function getClearSettings(signal?: AbortSignal) {
  return api.get<ClearSettings>(`${BASE}/clear-settings`, signal)
}

export function updateClearSettings(data: Partial<ClearSettings>) {
  return api.put<ClearSettings>(`${BASE}/clear-settings`, data)
}

export function testUpstreamConnection() {
  return api.post<{ ok: boolean }>(`${BASE}/clear-settings/test-upstream`)
}

// --- Users ---
export function listUsers(params: { limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  return api.get<ListEnvelope<User>>(`${BASE}/users?${q}`, signal)
}

export function createUser(email: string, role: string) {
  return api.post<User>(`${BASE}/users`, { email, role })
}

export function deleteUser(id: number) {
  return api.delete(`${BASE}/users/${id}`)
}
