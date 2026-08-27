// Shared browser-side API stand-in for the two capture harnesses
// (`capture-screens.mjs`, `capture-owner-review.mjs`).
//
// Both harnesses render the real built app and need the same thing from the
// server: a seated admin and a plausible row of every list. Keeping one copy
// means a screen added to either harness gets the same data, and a fixture fixed
// for one is fixed for both — the owner-review bundle compares two builds, so a
// fixture that drifts between harnesses would read as a design difference.
//
// Nothing here talks to PHP. `mockApi` fulfils every fetch/XHR the app makes, so
// a capture run needs no backend and no database (#440).

// ── admin JWT (decodable by the app's getUserRole / isAdmin; signature unused) ──
const b64u = (o) => Buffer.from(JSON.stringify(o)).toString('base64url')
export const TOKEN = `${b64u({ alg: 'HS256', typ: 'JWT' })}.${b64u({ sub: 1, org: 7, role: 'superadmin', iat: 0, exp: 4102444800 })}.sig`

/** The session key the app reads (`shared/api/client`). */
export const TOKEN_KEY = 'nene_clear_token'

// ── fixtures ─────────────────────────────────────────────────────────────────
const list = (items, total) => ({ items, total: total ?? items.length, limit: 50, offset: 0 })
const tx = (o) => ({ bank_transaction_id: 1, organization_id: 7, bank_import_batch_id: 1, bank_account_id: 1, value_date: '2026-04-20', amount_cents: 110000, counterparty_text: 'カ）アクメ', status: 'unmatched', ...o })
const batch = (o) => ({ bank_import_batch_id: 1, organization_id: 7, bank_account_id: 1, file_hash: 'a1b2c3', source_filename: 'april.csv', row_count: 12, status: 'imported', imported_at: '2026-04-21 09:00:00', imported_by: 1, reversed_at: null, reversal_reason: null, ...o })
const recon = (o) => ({ payment_reconciliation_id: 1, organization_id: 7, bank_transaction_id: 1, status: 'confirmed', reason_code: null, confirmed_by: 1, confirmed_at: '2026-04-21 10:00:00', reversed_at: null, reversal_reason: null, allocations: [{ invoice_id: 123, amount_cents: 110000, payment_id: 9 }], ...o })
const credit = (o) => ({ client_credit_id: 1, organization_id: 7, client_id: 45, amount_cents: 50000, remaining_cents: 50000, status: 'open', source_bank_transaction_id: 3, reconciliation_id: 1, created_by: 1, created_at: '2026-04-21 10:00:00', ...o })
const dunning = (o) => ({ dunning_notice_id: 1, organization_id: 7, invoice_id: 123, invoice_number: 'INV-2026-001', client_id: 45, recipient_email: 'accounts@acme.example', outstanding_at_send_cents: 110000, due_at: '2026-04-30', channel: 'log', sent_by: 1, sent_at: '2026-04-25 09:00:00', ...o })
const invoice = (o) => ({ invoice_id: 123, invoice_number: 'INV-2026-009', client_id: 45, issued_at: '2026-03-31', due_at: '2026-04-30', total_cents: 110000, outstanding_cents: 110000, status: 'overdue', currency: 'JPY', ...o })
const usr = (o) => ({ user_id: 1, organization_id: 7, email: 'member@acme.example', role: 'member', status: 'active', ...o })
const receivable = (o) => ({ manual_receivable_id: 1, organization_id: 7, source: 'manual', reference_number: 'MR-2026-004', client_name: '株式会社アクメ', recipient_email: 'ar@acme.example', total_cents: 132000, outstanding_cents: 132000, currency: 'JPY', issued_at: '2026-04-01', due_at: '2026-04-30', status: 'open', cancelled_at: null, created_by: 1, created_at: '2026-04-01 09:00:00', ...o })
const settings = { organization_id: 7, upstream_base_url: 'https://invoice.example.com', upstream_token_ref: 'NENE_INVOICE_BEARER_TOKEN', dunning_min_interval_days: 7, bank_accounts: [{ bank_account_id: 1, bank_name: 'みずほ銀行', bank_branch: '本店', account_type: 'ordinary', account_number: '1234567' }] }
const audit = (o) => ({ audit_event_id: 1, organization_id: 7, action: 'reconciliation_confirmed', entity_type: 'payment_reconciliation', entity_id: 1, actor_id: 1, occurred_at: '2026-04-21 10:00:00', before: { bank_transaction_status: 'unmatched' }, after: { bank_transaction_status: 'matched', total_allocated_cents: 110000 }, metadata: null, ...o })

const txs = [
  tx({ bank_transaction_id: 1, status: 'unmatched' }),
  tx({ bank_transaction_id: 2, amount_cents: 50000, counterparty_text: 'カ）サクラ商事', value_date: '2026-04-21', status: 'matched' }),
  tx({ bank_transaction_id: 3, amount_cents: 8000, counterparty_text: 'ヤマダタロウ', value_date: '2026-04-22', status: 'partially_matched' }),
  tx({ bank_transaction_id: 4, amount_cents: 220000, counterparty_text: 'カ）ミドリ', value_date: '2026-04-23', status: 'unmatched' }),
]
const invoices = [
  invoice({ invoice_id: 123, invoice_number: 'INV-2026-009', outstanding_cents: 110000, status: 'overdue' }),
  invoice({ invoice_id: 124, invoice_number: 'INV-2026-010', outstanding_cents: 50000, status: 'issued', due_at: '2026-05-10' }),
]
const users = [
  usr({ user_id: 1, email: 'admin@acme.example', role: 'admin', status: 'active' }),
  usr({ user_id: 2, email: 'tanaka@acme.example', role: 'member', status: 'active' }),
  usr({ user_id: 3, email: 'sato@acme.example', role: 'viewer', status: 'invited' }),
]
const receivables = [
  receivable({ manual_receivable_id: 1, status: 'open' }),
  receivable({ manual_receivable_id: 2, reference_number: 'MR-2026-005', client_name: 'サクラ商事', total_cents: 88000, outstanding_cents: 44000, status: 'partially_paid', due_at: '2026-05-15' }),
  receivable({ manual_receivable_id: 3, reference_number: 'MR-2026-006', client_name: '緑川工業', total_cents: 45000, outstanding_cents: 0, status: 'paid', due_at: '2026-04-10' }),
  receivable({ manual_receivable_id: 4, reference_number: 'MR-2026-007', client_name: 'ヤマダタロウ', recipient_email: null, total_cents: 12000, outstanding_cents: 12000, status: 'cancelled', due_at: null, cancelled_at: '2026-04-18 11:00:00' }),
]
const audits = [
  audit({ audit_event_id: 5, action: 'reconciliation_confirmed', entity_type: 'payment_reconciliation', entity_id: 1 }),
  audit({ audit_event_id: 4, action: 'dunning_sent', entity_type: 'dunning_notice', entity_id: 7, before: null, after: { invoice_number: 'INV-2026-001', recipient_email: 'accounts@acme.example', channel: 'log' } }),
  audit({ audit_event_id: 3, action: 'user_updated', entity_type: 'user', entity_id: 2, before: { role: 'viewer' }, after: { role: 'member' }, metadata: { user_id: 2, email: 'member@acme.example' } }),
  audit({ audit_event_id: 2, action: 'clear_settings_updated', entity_type: 'clear_settings', entity_id: 7, before: { dunning_min_interval_days: 14 }, after: { dunning_min_interval_days: 7 } }),
  audit({ audit_event_id: 1, action: 'login_succeeded', entity_type: 'user', entity_id: 1, before: null, after: { email: 'admin@acme.example' } }),
]

export function payloadFor(pathname, method, empty) {
  const E = (items) => list(empty ? [] : items)
  // The invitee has no session, so this one is matched before /auth/me.
  if (pathname.endsWith('/auth/invitation')) return { email: 'sato@acme.example' }
  if (pathname.endsWith('/auth/me')) return usr({ user_id: 1, email: 'admin@acme.example', role: 'superadmin' })
  if (pathname.includes('/bank-transactions/unmatched')) return E(txs.filter((t) => t.status !== 'matched'))
  if (pathname.includes('/bank-transactions')) return E(txs)
  if (pathname.includes('/bank-import-batches')) return E([batch({ bank_import_batch_id: 1 }), batch({ bank_import_batch_id: 2, source_filename: 'march.csv', status: 'reversed', reversed_at: '2026-04-01 10:00:00', reversal_reason: '誤取込' })])
  if (pathname.includes('/reconciliations/propose')) return { invoices }
  if (pathname.includes('/reconciliations')) return E([recon({ payment_reconciliation_id: 1 }), recon({ payment_reconciliation_id: 2, status: 'reversed', reversed_at: '2026-04-22 09:00:00', reversal_reason: '入金取消' })])
  if (pathname.includes('/client-credits')) return E([credit({ client_credit_id: 1 }), credit({ client_credit_id: 2, client_id: 88, amount_cents: 12000, remaining_cents: 4000, status: 'open' })])
  if (pathname.includes('/manual-receivables')) return E(receivables)
  if (pathname.includes('/upstream/invoices')) return { items: empty ? [] : invoices, total: empty ? 0 : invoices.length }
  if (pathname.includes('/dunning-notices')) return E([dunning({ dunning_notice_id: 1 }), dunning({ dunning_notice_id: 2, invoice_number: 'INV-2026-002', recipient_email: 'ar@midori.example', channel: 'smtp' })])
  if (pathname.includes('/dunning-pauses')) return E([{ dunning_pause_id: 1, organization_id: 7, invoice_id: 200, paused_by: 1, paused_at: '2026-04-20 09:00:00', paused_reason: '請求金額の確認中', unpaused_by: null, unpaused_at: null }])
  if (pathname.includes('/audit-events')) return E(audits)
  if (pathname.includes('/clear-settings')) return settings
  if (pathname.includes('/users')) return E(users)
  return {}
}

/**
 * Fulfil every API call the page makes. Document navigations still go to the
 * preview server (they are not fetch/XHR), so the SPA boots normally.
 */
export async function mockApi(page, { empty = false } = {}) {
  await page.route('**/admin/**', async (route) => {
    const req = route.request()
    if (req.resourceType() !== 'fetch' && req.resourceType() !== 'xhr') return route.continue()
    const url = new URL(req.url())
    const status = req.method() === 'DELETE' ? 204 : 200
    const body = status === 204 ? '' : JSON.stringify(payloadFor(url.pathname, req.method(), empty))
    return route.fulfill({ status, contentType: status === 204 ? 'text/plain' : 'application/json', body })
  })
}
