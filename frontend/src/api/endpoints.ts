import { api } from './client'
import type {
  User, BankImportBatch, BankTransaction, Reconciliation,
  ClientCredit, DunningNotice, ClearSettings, UpstreamInvoice,
  ListEnvelope,
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
export function listBankImportBatches(params: { limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
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
  counterparty?: string
  limit?: number
  offset?: number
}

export function listBankTransactions(filter: BankTransactionFilter, signal?: AbortSignal) {
  const q = new URLSearchParams()
  if (filter.status) q.set('status', filter.status)
  if (filter.value_date_from) q.set('value_date_from', filter.value_date_from)
  if (filter.value_date_to) q.set('value_date_to', filter.value_date_to)
  if (filter.counterparty) q.set('counterparty', filter.counterparty)
  q.set('limit', String(filter.limit ?? 50))
  q.set('offset', String(filter.offset ?? 0))
  return api.get<ListEnvelope<BankTransaction>>(`${BASE}/bank-transactions?${q}`, signal)
}

export function listUnmatchedTransactions(params: { limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  return api.get<ListEnvelope<BankTransaction>>(`${BASE}/bank-transactions/unmatched?${q}`, signal)
}

// --- Reconciliation ---
export function listReconciliations(params: { status?: string; limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  if (params.status) q.set('status', params.status)
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
  return api.post<Reconciliation>(
    `${BASE}/reconciliations/confirm`,
    { bank_transaction_id: bankTransactionId, allocations, reason_code: reasonCode },
  )
}

export function reverseReconciliation(id: number, reversalReason: string) {
  return api.post<void>(`${BASE}/reconciliations/${id}/reverse`, { reversal_reason: reversalReason })
}

// --- Client credits ---
export function listClientCredits(params: { limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  return api.get<ListEnvelope<ClientCredit>>(`${BASE}/client-credits?${q}`, signal)
}

export function applyClientCredit(creditId: number, invoiceId: number, amountCents: number) {
  return api.post<ClientCredit>(`${BASE}/client-credits/${creditId}/apply`, {
    invoice_id: invoiceId,
    amount_cents: amountCents,
  })
}

// --- Dunning ---
export function listDunningNotices(params: { limit?: number; offset?: number }, signal?: AbortSignal) {
  const q = new URLSearchParams({ limit: String(params.limit ?? 50), offset: String(params.offset ?? 0) })
  return api.get<ListEnvelope<DunningNotice>>(`${BASE}/dunning-notices?${q}`, signal)
}

export function sendDunningNotice(invoiceId: number) {
  return api.post<DunningNotice>(`${BASE}/dunning-notices`, { invoice_id: invoiceId })
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
