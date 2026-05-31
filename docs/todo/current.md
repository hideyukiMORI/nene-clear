# Current Work

Last updated: 2026-05-31

## Status

**Phase 1 — Complete** (payment reconciliation API).
**Phase 2 — Complete** (Admin UI + dunning; ja/en; professional review gate obtained).
**Infrastructure — Complete** (docker-compose + Mailpit; SmtpDunningMailer; InvoiceUpstreamHttpClient; login throttle).
**Security — Assessed** (2-round multi-tenant pentest; 4 findings fixed incl. one critical privilege escalation; `docs/security/assessment-2026-05.md`).
**nene-invoice upstream — Their side implemented (PR #141); Clear client + contract tests ready. Real connection (set env vars) still pending.**
**Bug fixes + feature gap closure — 12 issues (PR #99–#103) filed and addressed (open PRs, not yet merged).**

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

## Open PRs (pending review + merge)

| PR | Description |
| --- | --- |
| #99 | fix: 3 bugs — overdue dunning eligibility, ClientCredit clientId, channel recording |
| #100 | feat: template_version on DunningNotice (migration + entity + UseCase) |
| #101 | feat: 6 frontend gaps — proposeMatch UI, dunning invoice browser, client credits page, amount filter, settings bank/upstream |
| #102 | feat: CSV export endpoints (reconciliations / client-credits / bank-transactions) |
| #103 | feat: dunning pause per invoice (dunning_pauses table + UseCase + UI) |

All PRs pass `composer check` (208 tests, PHPStan level 8) and `npm run check` (27 unit tests).
Note: PRs are independent branches from main; they will have merge conflicts with each other — merge sequentially.

## Next steps

1. **Merge PRs #99–#103** — sequential merge (order above). Resolve conflicts per NENE2 architecture rules.
2. **Activate real Invoice upstream** — set env vars, run contract tests live.
3. **Dunning template customization** — operator-editable templates per org
   (currently a single hardcoded `lang/ja.php` template).
4. **CSV export — tax advisor sign-off** — review column set per compliance §9 before calling it production-ready.
5. **Phase 3 — Tier A shared hosting** — web installer, release ZIP, two-app
   (Invoice + Clear) operator setup guide.
6. **Phase 4 — Ecosystem** — MCP tools (`listUnmatchedTransactions`,
   `proposeMatch`, `sendDunningNotice`).

## Recently completed (this cycle)

- i18n message catalog (ja/en), multi-tenant SQL scoping, audit before/after
  snapshots, reconciliation idempotency, operator guide (電子帳簿保存法 §3.4).
- Admin UI (login, dashboard, bank import, transactions, reconciliation,
  dunning, settings, users) with full test coverage.
- Security hardening: login rate limiting, role-assignment privilege-escalation
  guard, SMTP credential handling, upstream error masking.
- CI pipelines + this status refresh.
- Bug fixes + feature gap closure: 12 issues identified and resolved in PRs #99–#103.
