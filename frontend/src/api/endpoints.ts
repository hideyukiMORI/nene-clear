import { api, apiFetch } from './client'

export async function downloadCsv(path: string, filename: string): Promise<void> {
  const res = await apiFetch(path)
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
  AuditEvent, ListEnvelope, ManualReceivable, ManualReceivableImportResult,
} from '@/types'

const BASE = '/admin'

/** Append a query string to a path when there are any params. */
function withQuery(path: string, params: URLSearchParams): string {
  const s = params.toString()
  return s === '' ? path : `${path}?${s}`
}

// --- Auth ---
export function login(email: string, password: string) {
  return api.post<{ token: string }>(`${BASE}/auth/login`, { email, password })
}

export function getCurrentUser(signal?: AbortSignal) {
  return api.get<User>(`${BASE}/auth/me`, signal)
}

// --- Invitation onboarding (public; the invitee has no session yet) ---
export function getInvitation(token: string, signal?: AbortSignal) {
  return api.get<{ email: string }>(`${BASE}/auth/invitation?token=${encodeURIComponent(token)}`, signal)
}

export function acceptInvitation(token: string, password: string) {
  return api.post<User>(`${BASE}/auth/invitation/accept`, { token, password })
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
  const res = await apiFetch(`${BASE}/bank-import-batches`, {
    method: 'POST',
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

// Filter + sort params (no pagination) — shared by the list query and the CSV
// export path so the export always mirrors what the page is showing.
function bankTransactionQuery(filter: BankTransactionFilter): URLSearchParams {
  const q = new URLSearchParams()
  if (filter.status) q.set('status', filter.status)
  if (filter.value_date_from) q.set('value_date_from', filter.value_date_from)
  if (filter.value_date_to) q.set('value_date_to', filter.value_date_to)
  if (filter.amount_min_cents != null) q.set('amount_min_cents', String(filter.amount_min_cents))
  if (filter.amount_max_cents != null) q.set('amount_max_cents', String(filter.amount_max_cents))
  if (filter.counterparty) q.set('counterparty', filter.counterparty)
  if (filter.sortBy) q.set('sort_by', filter.sortBy)
  if (filter.sortDir) q.set('sort_dir', filter.sortDir)
  return q
}

export function listBankTransactions(filter: BankTransactionFilter, signal?: AbortSignal) {
  const q = bankTransactionQuery(filter)
  q.set('limit', String(filter.limit ?? 50))
  q.set('offset', String(filter.offset ?? 0))
  return api.get<ListEnvelope<BankTransaction>>(`${BASE}/bank-transactions?${q}`, signal)
}

export function bankTransactionsExportPath(filter: BankTransactionFilter): string {
  return withQuery(`${BASE}/export/bank-transactions`, bankTransactionQuery(filter))
}

export interface UnmatchedQuery {
  valueDateFrom?: string
  valueDateTo?: string
  amountMinCents?: number
  amountMaxCents?: number
  counterparty?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listUnmatchedTransactions(params: UnmatchedQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.valueDateFrom) q.set('value_date_from', params.valueDateFrom)
  if (params.valueDateTo) q.set('value_date_to', params.valueDateTo)
  if (params.amountMinCents != null) q.set('amount_min_cents', String(params.amountMinCents))
  if (params.amountMaxCents != null) q.set('amount_max_cents', String(params.amountMaxCents))
  if (params.counterparty) q.set('counterparty', params.counterparty)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
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

function reconciliationQuery(params: ReconciliationQuery): URLSearchParams {
  const q = new URLSearchParams()
  if (params.status) q.set('status', params.status)
  if (params.bankTransactionId) q.set('bank_transaction_id', params.bankTransactionId)
  if (params.invoiceId) q.set('invoice_id', params.invoiceId)
  if (params.confirmedFrom) q.set('confirmed_from', params.confirmedFrom)
  if (params.confirmedTo) q.set('confirmed_to', params.confirmedTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return q
}

export function listReconciliations(params: ReconciliationQuery, signal?: AbortSignal) {
  const q = reconciliationQuery(params)
  q.set('limit', String(params.limit ?? 50))
  q.set('offset', String(params.offset ?? 0))
  return api.get<ListEnvelope<Reconciliation>>(`${BASE}/reconciliations?${q}`, signal)
}

export function reconciliationsExportPath(params: ReconciliationQuery): string {
  return withQuery(`${BASE}/export/reconciliations`, reconciliationQuery(params))
}

export function getReconciliation(id: number, signal?: AbortSignal) {
  return api.get<Reconciliation>(`${BASE}/reconciliations/${id}`, signal)
}

export type ReceivableSource = 'invoice_upstream' | 'manual'

/** A confirm allocation targets an upstream invoice OR a manual receivable (ADR 0014). */
export interface AllocationInput {
  source: ReceivableSource
  invoice_id?: number
  manual_receivable_id?: number
  amount_cents: number
}

/** A ranked propose candidate; upstream or manual (ADR 0014). */
export interface MatchSuggestion {
  source: ReceivableSource
  invoice_id: number | null
  invoice_number: string | null
  manual_receivable_id: number | null
  reference_number: string | null
  amount_cents: number
  outstanding_cents: number
  score: number
  reason: string
}

export function proposeMatch(bankTransactionId: number) {
  return api.post<{ bank_transaction_id: number; upstream_unavailable: boolean; suggestions: MatchSuggestion[] }>(
    `${BASE}/reconciliations/propose`,
    { bank_transaction_id: bankTransactionId },
  )
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

function clientCreditQuery(params: ClientCreditQuery): URLSearchParams {
  const q = new URLSearchParams()
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
  return q
}

export function listClientCredits(params: ClientCreditQuery, signal?: AbortSignal) {
  const q = clientCreditQuery(params)
  q.set('limit', String(params.limit ?? 50))
  q.set('offset', String(params.offset ?? 0))
  return api.get<ListEnvelope<ClientCredit>>(`${BASE}/client-credits?${q}`, signal)
}

export function clientCreditsExportPath(params: ClientCreditQuery): string {
  return withQuery(`${BASE}/export/client-credits`, clientCreditQuery(params))
}

export function applyClientCredit(creditId: number, invoiceId: number, amountCents: number) {
  return api.post<ClientCredit>(`${BASE}/client-credits/${creditId}/apply`, {
    invoice_id: invoiceId,
    amount_cents: amountCents,
  })
}

// --- Manual receivables (ADR 0014) ---
export interface ManualReceivableQuery {
  status?: string
  q?: string
  dueFrom?: string
  dueTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listManualReceivables(params: ManualReceivableQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.status) q.set('status', params.status)
  if (params.q) q.set('q', params.q)
  if (params.dueFrom) q.set('due_from', params.dueFrom)
  if (params.dueTo) q.set('due_to', params.dueTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<ManualReceivable>>(`${BASE}/manual-receivables?${q}`, signal)
}

export interface CreateManualReceivableInput {
  reference_number: string
  client_name: string
  total_cents: number
  recipient_email?: string
  issued_at?: string
  due_at?: string
}

export function createManualReceivable(input: CreateManualReceivableInput) {
  return api.post<ManualReceivable>(`${BASE}/manual-receivables`, input)
}

export function cancelManualReceivable(id: number) {
  return api.post<ManualReceivable>(`${BASE}/manual-receivables/${id}/cancel`)
}

export async function importManualReceivables(file: File): Promise<ManualReceivableImportResult> {
  const form = new FormData()
  form.append('file', file)
  const res = await apiFetch(`${BASE}/manual-receivable-imports`, {
    method: 'POST',
    body: form,
  })
  const data = await res.json().catch(() => null)
  if (!res.ok) {
    const detail = data && typeof data === 'object' && 'detail' in data ? String((data as { detail?: unknown }).detail ?? '') : ''
    const title = data && typeof data === 'object' && 'title' in data ? String((data as { title?: unknown }).title ?? '') : `Import failed (${res.status})`
    throw new Error(detail || title)
  }
  return data as ManualReceivableImportResult
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

function dunningNoticeQuery(params: DunningNoticeQuery): URLSearchParams {
  const q = new URLSearchParams()
  if (params.invoiceNumber) q.set('invoice_number', params.invoiceNumber)
  if (params.recipientEmail) q.set('recipient_email', params.recipientEmail)
  if (params.outstandingMinCents != null) q.set('outstanding_min_cents', String(params.outstandingMinCents))
  if (params.outstandingMaxCents != null) q.set('outstanding_max_cents', String(params.outstandingMaxCents))
  if (params.sentFrom) q.set('sent_from', params.sentFrom)
  if (params.sentTo) q.set('sent_to', params.sentTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return q
}

export function listDunningNotices(params: DunningNoticeQuery, signal?: AbortSignal) {
  const q = dunningNoticeQuery(params)
  q.set('limit', String(params.limit ?? 50))
  q.set('offset', String(params.offset ?? 0))
  return api.get<ListEnvelope<DunningNotice>>(`${BASE}/dunning-notices?${q}`, signal)
}

export function dunningNoticesExportPath(params: DunningNoticeQuery): string {
  return withQuery(`${BASE}/export/dunning-notices`, dunningNoticeQuery(params))
}

// --- Audit trail (admin) ---
export interface AuditQuery {
  action?: string
  actorId?: string
  occurredFrom?: string
  occurredTo?: string
  sortBy?: string
  sortDir?: string
  limit?: number
  offset?: number
}

export function listAuditEvents(params: AuditQuery, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.action) q.set('action', params.action)
  if (params.actorId) q.set('actor_id', params.actorId)
  if (params.occurredFrom) q.set('occurred_from', params.occurredFrom)
  if (params.occurredTo) q.set('occurred_to', params.occurredTo)
  if (params.sortBy) q.set('sort_by', params.sortBy)
  if (params.sortDir) q.set('sort_dir', params.sortDir)
  return api.get<ListEnvelope<AuditEvent>>(`${BASE}/audit-events?${q}`, signal)
}

export type DunningStage = 'initial' | 'reminder' | 'final'

export function sendDunningNotice(invoiceId: number, stage: DunningStage = 'initial') {
  return api.post<DunningNotice>(`${BASE}/dunning-notices`, { invoice_id: invoiceId, stage })
}

export interface DunningPreview {
  invoice_number: string
  recipient_email: string
  stage: string
  subject: string
  body: string
  template_version: string
}

export function previewDunningNotice(invoiceId: number, stage: DunningStage = 'initial', signal?: AbortSignal) {
  return api.get<DunningPreview>(`${BASE}/dunning-notices/preview?invoice_id=${invoiceId}&stage=${stage}`, signal)
}

export function testSendDunningNotice(invoiceId: number, to: string, stage: DunningStage = 'initial') {
  return api.post<{ sent_to: string }>(`${BASE}/dunning-notices/test-send`, { invoice_id: invoiceId, to, stage })
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
