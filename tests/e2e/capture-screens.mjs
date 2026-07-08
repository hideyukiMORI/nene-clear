// Capture every admin page (and key UI states) of the running app as standalone
// HTML files for design review. Renders the built app served by `vite preview`
// on http://localhost:4174 (same target as playwright.config.ts), mocks the API,
// and writes each rendered #root with the app's compiled CSS so each file is
// independently viewable. Output: /tmp/nene-clear-screens/
//
// Prerequisite: the preview server must already be running, e.g.
//   (cd ../../frontend && npm run build && npm run preview -- --port 4174 --strictPort)
// then from tests/e2e/:  npm run screens   (or: node capture-screens.mjs)
import { chromium } from '@playwright/test'
import { readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const HERE = dirname(fileURLToPath(import.meta.url))
const REPO = resolve(HERE, '../..')
const BASE = 'http://localhost:4174'
const OUT = '/tmp/nene-clear-screens'

rmSync(OUT, { recursive: true, force: true })
mkdirSync(OUT, { recursive: true })

// ── admin JWT (decodable by the app's getUserRole / isAdmin; signature unused) ──
const b64u = (o) => Buffer.from(JSON.stringify(o)).toString('base64url')
const TOKEN = `${b64u({ alg: 'HS256', typ: 'JWT' })}.${b64u({ sub: 1, org: 7, role: 'superadmin', iat: 0, exp: 4102444800 })}.sig`

// ── icon sprite (from index.html) so <use href="#i-..."> resolves standalone ──
const indexHtml = readFileSync(resolve(REPO, 'frontend/index.html'), 'utf8')
const sprite = indexHtml.match(/<svg width="0" height="0"[\s\S]*?<\/svg>/)?.[0] ?? ''

// ── fixtures ─────────────────────────────────────────────────────────────────
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
const audits = [
  audit({ audit_event_id: 5, action: 'reconciliation_confirmed', entity_type: 'payment_reconciliation', entity_id: 1 }),
  audit({ audit_event_id: 4, action: 'dunning_sent', entity_type: 'dunning_notice', entity_id: 7, before: null, after: { invoice_number: 'INV-2026-001', recipient_email: 'accounts@acme.example', channel: 'log' } }),
  audit({ audit_event_id: 3, action: 'user_updated', entity_type: 'user', entity_id: 2, before: { role: 'viewer' }, after: { role: 'member' }, metadata: { user_id: 2, email: 'member@acme.example' } }),
  audit({ audit_event_id: 2, action: 'clear_settings_updated', entity_type: 'clear_settings', entity_id: 7, before: { dunning_min_interval_days: 14 }, after: { dunning_min_interval_days: 7 } }),
  audit({ audit_event_id: 1, action: 'login_succeeded', entity_type: 'user', entity_id: 1, before: null, after: { email: 'admin@acme.example' } }),
]

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

function htmlDoc(title, rootHtml) {
  return `<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeNe Clear — ${title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./app.css">
</head>
<body data-theme="a">
${sprite}
${rootHtml}
</body>
</html>
`
}

// ── scenarios ────────────────────────────────────────────────────────────────
const SCENARIOS = [
  { file: 'page-login', title: 'Login', route: '/login', token: false, group: 'Pages' },
  { file: 'page-dashboard', title: 'Dashboard / ダッシュボード', route: '/admin', group: 'Pages' },
  { file: 'page-bank-import', title: 'Bank import / 入金CSV取込', route: '/admin/bank-import', group: 'Pages' },
  { file: 'page-bank-transactions', title: 'Bank transactions / 入金明細', route: '/admin/bank-transactions', group: 'Pages' },
  { file: 'page-reconciliation', title: 'Reconciliation / 消込', route: '/admin/reconciliation', group: 'Pages' },
  { file: 'page-client-credits', title: 'Client credits / 預り金', route: '/admin/client-credits', group: 'Pages' },
  { file: 'page-dunning', title: 'Dunning / 督促', route: '/admin/dunning', group: 'Pages' },
  { file: 'page-settings', title: 'Settings / 設定', route: '/admin/settings', group: 'Pages' },
  { file: 'page-users', title: 'Users / ユーザー管理', route: '/admin/users', group: 'Pages' },
  { file: 'page-audit-log', title: 'Audit log / 監査ログ', route: '/admin/audit-log', group: 'Pages' },
  // states / parts
  { file: 'state-bank-transactions-empty', title: 'Bank transactions — empty state', route: '/admin/bank-transactions', empty: true, group: 'States' },
  { file: 'state-users-empty', title: 'Users — empty state', route: '/admin/users', empty: true, group: 'States' },
  { file: 'state-audit-empty', title: 'Audit log — empty state', route: '/admin/audit-log', empty: true, group: 'States' },
  { file: 'state-users-invite-modal', title: 'Users — invite modal', route: '/admin/users', group: 'States', action: async (page) => { await page.getByRole('button', { name: /招待|invite/i }).first().click() } },
  { file: 'state-dunning-send-modal', title: 'Dunning — send modal', route: '/admin/dunning', group: 'States', action: async (page) => { await page.getByRole('button', { name: /送信|send/i }).first().click() } },
  { file: 'state-reconciliation-confirm-modal', title: 'Reconciliation — match modal', route: '/admin/reconciliation', group: 'States', action: async (page) => { await page.getByRole('button', { name: '消込を確定' }).first().click(); await page.waitForSelector('[role="dialog"]', { timeout: 5000 }) } },
]

const browser = await chromium.launch()
let appCssWritten = false
const done = []

for (const s of SCENARIOS) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 960 } })
  if (s.token !== false) {
    await context.addInitScript((t) => sessionStorage.setItem('nene_clear_token', t), TOKEN)
  }
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
    // Wait for the app to actually mount before snapshotting (avoids blank #root).
    const anchor = s.route === '/login' ? '.login-card' : '.side-nav'
    await page.waitForSelector(anchor, { timeout: 15000 })
    await page.waitForLoadState('networkidle').catch(() => {})
    await page.waitForTimeout(900)
    if (s.action) {
      try { await s.action(page); await page.waitForTimeout(400) } catch (e) { console.log(`  (action skipped for ${s.file}: ${e.message.split('\n')[0]})`) }
    }

    if (!appCssWritten) {
      const css = await page.evaluate(() => {
        let out = ''
        for (const sheet of document.styleSheets) {
          try { for (const rule of sheet.cssRules) out += rule.cssText + '\n' } catch (e) { /* cross-origin */ }
        }
        return out
      })
      writeFileSync(`${OUT}/app.css`, css)
      appCssWritten = true
    }

    const root = await page.evaluate(() => document.getElementById('root')?.outerHTML ?? '<div id="root"></div>')
    writeFileSync(`${OUT}/${s.file}.html`, htmlDoc(s.title, root))
    done.push(s)
    console.log(`✓ ${s.file}`)
  } catch (e) {
    console.log(`✗ ${s.file}: ${e.message.split('\n')[0]}`)
  }
  await context.close()
}

// ── index gallery ─────────────────────────────────────────────────────────────
const groups = [...new Set(done.map((s) => s.group))]
const cards = (g) => done.filter((s) => s.group === g).map((s) =>
  `      <a class="card" href="./${s.file}.html"><span class="t">${s.title}</span><span class="f">${s.file}.html</span></a>`).join('\n')
const index = `<!doctype html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NeNe Clear — Screens</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+JP:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body{ margin:0; font-family:"Noto Sans JP",Inter,system-ui,sans-serif; background:#f4f6f9; color:#1b2a3d; padding:40px clamp(20px,4vw,64px); }
  h1{ font-size:24px; margin:0 0 6px; } .lede{ color:#5a6b80; margin:0 0 28px; font-size:14px; }
  h2{ font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:#7186a0; margin:30px 0 12px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px; }
  .card{ display:flex; flex-direction:column; gap:4px; padding:16px 18px; background:#fff; border:1px solid #e2e8f0;
    border-radius:8px; text-decoration:none; color:inherit; transition:.12s; }
  .card:hover{ border-color:#3d6ba3; box-shadow:0 4px 14px rgba(20,40,70,.08); transform:translateY(-1px); }
  .card .t{ font-weight:600; font-size:14px; } .card .f{ font-family:ui-monospace,monospace; font-size:11.5px; color:#8094ab; }
</style></head>
<body>
  <h1>NeNe Clear — UI screens</h1>
  <p class="lede">Standalone snapshots of the live admin UI (real rendered DOM + the app's compiled <code>app.css</code>). Each file opens independently for design review.</p>
${groups.map((g) => `  <h2>${g}</h2>\n  <div class="grid">\n${cards(g)}\n  </div>`).join('\n')}
</body></html>
`
writeFileSync(`${OUT}/index.html`, index)

await browser.close()
console.log(`\nDone: ${done.length}/${SCENARIOS.length} screens → ${OUT}`)
