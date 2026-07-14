// Before/after cascade-regression harness for the @layer legacy isolation (W1).
// Same scenarios/mocks as capture-screens.mjs. For each scenario writes:
//   <OUT>/<label>/<file>.png            full-page screenshot
//   <OUT>/<label>/<file>.computed.json  per-element getComputedStyle fingerprint
// Run label=before (design.css unlayered), then label=after (wrapped in @layer legacy).
// Prereq: preview server on http://localhost:4174.
import { chromium } from '@playwright/test'
import { writeFileSync, mkdirSync, rmSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const LABEL = process.argv[2]
if (LABEL !== 'before' && LABEL !== 'after') { console.error('usage: node compare-layer.mjs <before|after>'); process.exit(2) }
const HERE = dirname(fileURLToPath(import.meta.url))
const BASE = 'http://localhost:4174'
const OUT = resolve(process.env.CMP_OUT || '/tmp/nene-clear-cmp', LABEL)
rmSync(OUT, { recursive: true, force: true }); mkdirSync(OUT, { recursive: true })

const b64u = (o) => Buffer.from(JSON.stringify(o)).toString('base64url')
const TOKEN = `${b64u({ alg: 'HS256', typ: 'JWT' })}.${b64u({ sub: 1, org: 7, role: 'superadmin', iat: 0, exp: 4102444800 })}.sig`

const list = (items, total) => ({ items, total: total ?? items.length, limit: 50, offset: 0 })
const tx = (o) => ({ bank_transaction_id: 1, organization_id: 7, bank_import_batch_id: 1, bank_account_id: 1, value_date: '2026-04-20', amount_cents: 110000, counterparty_text: 'カ）アクメ', status: 'unmatched', ...o })
const batch = (o) => ({ bank_import_batch_id: 1, organization_id: 7, bank_account_id: 1, file_hash: 'a1b2c3', source_filename: 'april.csv', row_count: 12, status: 'imported', imported_at: '2026-04-21 09:00:00', imported_by: 1, reversed_at: null, reversal_reason: null, ...o })
const recon = (o) => ({ payment_reconciliation_id: 1, organization_id: 7, bank_transaction_id: 1, status: 'confirmed', reason_code: null, confirmed_by: 1, confirmed_at: '2026-04-21 10:00:00', reversed_at: null, reversal_reason: null, allocations: [{ invoice_id: 123, amount_cents: 110000, payment_id: 9 }], ...o })
const credit = (o) => ({ client_credit_id: 1, organization_id: 7, client_id: 45, amount_cents: 50000, remaining_cents: 50000, status: 'open', source_bank_transaction_id: 3, reconciliation_id: 1, created_by: 1, created_at: '2026-04-21 10:00:00', ...o })
const dunning = (o) => ({ dunning_notice_id: 1, organization_id: 7, invoice_id: 123, invoice_number: 'INV-2026-001', client_id: 45, recipient_email: 'accounts@acme.example', outstanding_at_send_cents: 110000, due_at: '2026-04-30', channel: 'log', sent_by: 1, sent_at: '2026-04-25 09:00:00', ...o })
const invoice = (o) => ({ invoice_id: 123, invoice_number: 'INV-2026-009', client_id: 45, issued_at: '2026-03-31', due_at: '2026-04-30', total_cents: 110000, outstanding_cents: 110000, status: 'overdue', currency: 'JPY', ...o })
const usr = (o) => ({ user_id: 1, organization_id: 7, email: 'member@acme.example', role: 'member', status: 'active', ...o })
const settings = { organization_id: 7, upstream_base_url: 'https://invoice.example.com', upstream_token_ref: 'NENE_INVOICE_BEARER_TOKEN', dunning_min_interval_days: 7, bank_accounts: [{ bank_account_id: 1, bank_name: 'みずほ銀行', bank_branch: '本店', account_type: 'ordinary', account_number: '1234567' }] }
const audit = (o) => ({ audit_event_id: 1, organization_id: 7, action: 'reconciliation_confirmed', entity_type: 'payment_reconciliation', entity_id: 1, actor_id: 1, occurred_at: '2026-04-21 10:00:00', before: { bank_transaction_status: 'unmatched' }, after: { bank_transaction_status: 'matched', total_allocated_cents: 110000 }, metadata: null, ...o })
const txs = [tx({ bank_transaction_id: 1, status: 'unmatched' }), tx({ bank_transaction_id: 2, amount_cents: 50000, counterparty_text: 'カ）サクラ商事', value_date: '2026-04-21', status: 'matched' }), tx({ bank_transaction_id: 3, amount_cents: 8000, counterparty_text: 'ヤマダタロウ', value_date: '2026-04-22', status: 'partially_matched' }), tx({ bank_transaction_id: 4, amount_cents: 220000, counterparty_text: 'カ）ミドリ', value_date: '2026-04-23', status: 'unmatched' })]
const invoices = [invoice({ invoice_id: 123, invoice_number: 'INV-2026-009', outstanding_cents: 110000, status: 'overdue' }), invoice({ invoice_id: 124, invoice_number: 'INV-2026-010', outstanding_cents: 50000, status: 'issued', due_at: '2026-05-10' })]
const users = [usr({ user_id: 1, email: 'admin@acme.example', role: 'admin', status: 'active' }), usr({ user_id: 2, email: 'tanaka@acme.example', role: 'member', status: 'active' }), usr({ user_id: 3, email: 'sato@acme.example', role: 'viewer', status: 'invited' })]
const audits = [audit({ audit_event_id: 5, action: 'reconciliation_confirmed', entity_type: 'payment_reconciliation', entity_id: 1 }), audit({ audit_event_id: 4, action: 'dunning_sent', entity_type: 'dunning_notice', entity_id: 7, before: null, after: { invoice_number: 'INV-2026-001', recipient_email: 'accounts@acme.example', channel: 'log' } }), audit({ audit_event_id: 3, action: 'user_updated', entity_type: 'user', entity_id: 2, before: { role: 'viewer' }, after: { role: 'member' }, metadata: { user_id: 2, email: 'member@acme.example' } }), audit({ audit_event_id: 2, action: 'clear_settings_updated', entity_type: 'clear_settings', entity_id: 7, before: { dunning_min_interval_days: 14 }, after: { dunning_min_interval_days: 7 } }), audit({ audit_event_id: 1, action: 'login_succeeded', entity_type: 'user', entity_id: 1, before: null, after: { email: 'admin@acme.example' } })]

function payloadFor(pathname, method, empty) {
  const E = (items) => list(empty ? [] : items)
  if (pathname.endsWith('/auth/me')) return usr({ user_id: 1, email: 'admin@acme.example', role: 'superadmin' })
  if (pathname.includes('/bank-transactions/unmatched')) return E(txs.filter((t) => t.status !== 'matched'))
  if (pathname.includes('/bank-transactions')) return E(txs)
  if (pathname.includes('/bank-import-batches')) return E([batch({ bank_import_batch_id: 1 }), batch({ bank_import_batch_id: 2, source_filename: 'march.csv', status: 'reversed', reversed_at: '2026-04-01 10:00:00', reversal_reason: '誤取込' })])
  if (pathname.includes('/reconciliations/propose')) return { invoices }
  if (pathname.includes('/reconciliations')) return E([recon({ payment_reconciliation_id: 1 }), recon({ payment_reconciliation_id: 2, status: 'reversed', reversed_at: '2026-04-22 09:00:00', reversal_reason: '入金取消' })])
  if (pathname.includes('/client-credits')) return E([credit({ client_credit_id: 1 }), credit({ client_credit_id: 2, client_id: 88, amount_cents: 12000, remaining_cents: 4000, status: 'open' })])
  if (pathname.includes('/upstream/invoices')) return { items: empty ? [] : invoices, total: empty ? 0 : invoices.length }
  if (pathname.includes('/dunning-notices')) return E([dunning({ dunning_notice_id: 1 }), dunning({ dunning_notice_id: 2, invoice_number: 'INV-2026-002', recipient_email: 'ar@midori.example', channel: 'smtp' })])
  if (pathname.includes('/dunning-pauses')) return E([{ dunning_pause_id: 1, organization_id: 7, invoice_id: 200, paused_by: 1, paused_at: '2026-04-20 09:00:00', paused_reason: '請求金額の確認中', unpaused_by: null, unpaused_at: null }])
  if (pathname.includes('/audit-events')) return E(audits)
  if (pathname.includes('/clear-settings')) return settings
  if (pathname.includes('/users')) return E(users)
  return {}
}

const SCENARIOS = [
  { file: 'page-login', route: '/login', token: false },
  { file: 'page-dashboard', route: '/admin' },
  { file: 'page-bank-import', route: '/admin/bank-import' },
  { file: 'page-bank-transactions', route: '/admin/bank-transactions' },
  { file: 'page-reconciliation', route: '/admin/reconciliation' },
  { file: 'page-client-credits', route: '/admin/client-credits' },
  { file: 'page-dunning', route: '/admin/dunning' },
  { file: 'page-settings', route: '/admin/settings' },
  { file: 'page-users', route: '/admin/users' },
  { file: 'page-audit-log', route: '/admin/audit-log' },
  { file: 'state-bank-transactions-empty', route: '/admin/bank-transactions', empty: true },
  { file: 'state-users-empty', route: '/admin/users', empty: true },
  { file: 'state-audit-empty', route: '/admin/audit-log', empty: true },
  { file: 'state-users-invite-modal', route: '/admin/users', action: async (page) => { await page.getByRole('button', { name: /招待|invite/i }).first().click() } },
  { file: 'state-dunning-send-modal', route: '/admin/dunning', action: async (page) => { await page.getByRole('button', { name: /送信|send/i }).first().click() } },
  { file: 'state-reconciliation-confirm-modal', route: '/admin/reconciliation', action: async (page) => { await page.getByRole('button', { name: '消込を確定' }).first().click(); await page.waitForSelector('[role="dialog"]', { timeout: 5000 }) } },
]

const PROPS = ['color','background-color','background-image','opacity','font-family','font-size','font-weight','font-variant-numeric','line-height','letter-spacing','text-transform','text-align','text-decoration-line','display','position','box-sizing','visibility','margin-top','margin-right','margin-bottom','margin-left','padding-top','padding-right','padding-bottom','padding-left','border-top-width','border-right-width','border-bottom-width','border-left-width','border-top-color','border-top-style','border-radius','width','height','box-shadow','flex-direction','align-items','justify-content','gap','flex-grow','flex-shrink']

const browser = await chromium.launch()
const done = []
for (const s of SCENARIOS) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } })
  if (s.token !== false) await context.addInitScript((t) => sessionStorage.setItem('nene_clear_token', t), TOKEN)
  const page = await context.newPage()
  await page.route('**/admin/**', async (route) => {
    const req = route.request()
    if (req.resourceType() !== 'fetch' && req.resourceType() !== 'xhr') return route.continue()
    const url = new URL(req.url())
    const status = req.method() === 'DELETE' ? 204 : 200
    const body = status === 204 ? '' : JSON.stringify(payloadFor(url.pathname, req.method(), s.empty))
    return route.fulfill({ status, contentType: status === 204 ? 'text/plain' : 'application/json', body })
  })
  try {
    await page.goto(BASE + s.route, { waitUntil: 'domcontentloaded', timeout: 20000 })
    const anchor = s.route === '/login' ? '.login-card' : '.side-nav'
    await page.waitForSelector(anchor, { timeout: 15000 })
    await page.waitForLoadState('networkidle').catch(() => {})
    await page.waitForTimeout(900)
    if (s.action) { try { await s.action(page); await page.waitForTimeout(400) } catch (e) { console.log(`  (action skipped ${s.file}: ${e.message.split('\n')[0]})`) } }
    await page.screenshot({ path: `${OUT}/${s.file}.png`, fullPage: true })
    const fp = await page.evaluate((props) => {
      const out = []; const els = document.querySelectorAll('#root *, .modal, [role="dialog"]'); let i = 0
      for (const el of els) {
        const cs = getComputedStyle(el)
        const rec = { i, tag: el.tagName.toLowerCase(), cls: el.getAttribute('class') || '', s: {} }
        for (const p of props) rec.s[p] = cs.getPropertyValue(p)
        const r = el.getBoundingClientRect(); rec.rect = { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }
        out.push(rec); i++
      }
      return out
    }, PROPS)
    writeFileSync(`${OUT}/${s.file}.computed.json`, JSON.stringify(fp))
    done.push(s.file); console.log(`OK ${LABEL}/${s.file} (${fp.length} els)`)
  } catch (e) { console.log(`FAIL ${LABEL}/${s.file}: ${e.message.split('\n')[0]}`) }
  await context.close()
}
await browser.close()
console.log(`\n${LABEL}: ${done.length}/${SCENARIOS.length} captured -> ${OUT}`)
