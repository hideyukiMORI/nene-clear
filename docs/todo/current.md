# Current Work

Last updated: 2026-05-31

## Status

**Phase 1 — Complete** (payment reconciliation API).
**Phase 2 — Complete** (Admin UI + dunning; ja/en; professional review gate obtained).
**Infrastructure — Complete** (docker-compose + Mailpit; SmtpDunningMailer; InvoiceUpstreamHttpClient; login throttle).
**Security — Assessed** (2-round multi-tenant pentest; 4 findings fixed incl. one critical privilege escalation; `docs/security/assessment-2026-05.md`).
**nene-invoice upstream — Their side implemented (PR #141); Clear client + contract tests ready. Real connection (set env vars) still pending.**

### Tests / quality gates

| Layer | Count | Tool |
| --- | --- | --- |
| Backend | 208 (6 skipped) | PHPUnit; PHPStan level 8; PHP-CS-Fixer |
| Frontend unit | 27 | Vitest |
| Browser E2E | 43 | Playwright (API mocked) |

CI: GitHub Actions — `backend-ci`, `frontend-ci`, `e2e-ci` run on push/PR to main.
6 skipped backend tests = Invoice contract tests that auto-activate once
`NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` are set.

## What is running

| Domain | Endpoints | Auth |
| --- | --- | --- |
| Auth | `POST /admin/auth/login` (throttled), `GET /admin/auth/me` | JWT |
| Organization | CRUD `/admin/organizations` | `manage_organizations` |
| User | CRUD `/admin/users` | `manage_users` |
| ClearSettings | `GET/PUT /admin/clear-settings`, `POST /admin/clear-settings/test-upstream` | `manage_clear_settings` |
| Bank import | `POST /admin/bank-import-batches` (CSV), `GET` list, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Bank transactions | `GET /admin/bank-transactions` (filters), `GET /{id}`, `GET /unmatched` | `view_reconciliation` |
| Reconciliation | `POST /propose`, `POST /admin/reconciliations` (confirm), `GET` list/by-id, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Client credits | `GET /admin/client-credits`, `POST /{id}/apply` | `view_reconciliation` / `manage_reconciliation` |
| Dunning | `POST /admin/dunning-notices`, `GET` list/by-id | `send_dunning` / `view_reconciliation` |
| Admin UI | React SPA (`frontend/`), served from `public_html/assets/` | JWT (session storage) |

## Infrastructure

| Component | Status | Notes |
| --- | --- | --- |
| Docker | ✅ `docker-compose.yml` | MySQL 8.4 (port 3310) + Mailpit (SMTP 1025, web UI 8025) |
| SMTP mailer | ✅ `SmtpDunningMailer` | Activated when `SMTP_HOST` set; falls back to `LogOnlyDunningMailer`; credentials passed via EsmtpTransport (not DSN) |
| Invoice upstream | ✅ `InvoiceUpstreamHttpClient` | Activated when `NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` set; falls back to `FakeInvoiceUpstreamClient` |
| Login throttle | ✅ `PdoLoginThrottle` | 5 failures / 15 min per email+IP → 15 min lock (HTTP 429) |
| Contract tests | ✅ 6 tests (auto-skip) | Run against real Invoice API by setting env vars |
| CI | ✅ GitHub Actions | backend / frontend / e2e workflows |

## Open risks

1. **Real upstream connection not yet exercised** — `InvoiceUpstreamHttpClient` +
   contract tests are ready; set `NENE_INVOICE_API_BASE_URL` /
   `NENE_INVOICE_BEARER_TOKEN` against a live Invoice instance and run the
   contract suite to confirm the integration end-to-end.

## Next steps

1. **Activate real Invoice upstream** — set env vars, run contract tests live.
2. **Dunning template customization** — operator-editable templates per org
   (currently a single hardcoded `lang/ja.php` template).
3. **Phase 3 — Tier A shared hosting** — web installer, release ZIP, two-app
   (Invoice + Clear) operator setup guide.
4. **Phase 4 — Ecosystem** — MCP tools (`listUnmatchedTransactions`,
   `proposeMatch`, `sendDunningNotice`), CSV export for accounting software.

## Recently completed (this cycle)

- i18n message catalog (ja/en), multi-tenant SQL scoping, audit before/after
  snapshots, reconciliation idempotency, operator guide (電子帳簿保存法 §3.4).
- Admin UI (login, dashboard, bank import, transactions, reconciliation,
  dunning, settings, users) with full test coverage.
- Security hardening: login rate limiting, role-assignment privilege-escalation
  guard, SMTP credential handling, upstream error masking.
- CI pipelines + this status refresh.
