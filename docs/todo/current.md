# Current Work

Last updated: 2026-06-27

## Status

**Phase 1 — Complete** (payment reconciliation API).
**Phase 2 — Complete** (Admin UI + dunning; ja/en; professional review gate obtained).
**Infrastructure — Complete** (docker-compose + Mailpit; SmtpDunningMailer; InvoiceUpstreamHttpClient; login throttle).
**Security — Assessed** (2-round multi-tenant pentest; 4 findings fixed incl. one critical privilege escalation; `docs/security/assessment-2026-05.md`).
**Post-Phase-2 hardening — Merged** (PR #99–#118): bug fixes, 6 frontend gap closures, CSV export, per-invoice dunning pause, `template_version`, OpenAPI Dunning/Export spec, NENE2-compliant DI + soft-delete + full audit trail, admin audit-log page, shared `StatusBadge`/`Pager`, ClaudeDesign design system, E2E realign + a11y.
**nene-invoice upstream — Their side implemented (PR #141); Clear client + contract tests ready. Real connection (set env vars) still pending.**

### Tests / quality gates

| Layer | Count | Tool |
| --- | --- | --- |
| Backend | 236 (6 skipped) | PHPUnit; PHPStan level 8; PHP-CS-Fixer |
| Frontend unit | 27 | Vitest |
| Browser E2E | 43 | Playwright (API mocked) |

CI: GitHub Actions — `backend-ci`, `frontend-ci`, `e2e-ci` run on push/PR to main;
`close-issues` auto-closes linked Issues on rebase-merge to main.
6 skipped backend tests = Invoice contract tests that auto-activate once
`NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` are set.

## What is running

| Domain | Endpoints | Auth |
| --- | --- | --- |
| Auth | `POST /admin/auth/login` (throttled), `GET /admin/auth/me` | JWT |
| Organization | `GET/POST /admin/organizations`, `GET/DELETE /admin/organizations/{id}` | `manage_organizations` |
| User | `GET/POST /admin/users`, `GET/PUT/DELETE /admin/users/{id}` | `manage_users` |
| ClearSettings | `GET/PUT /admin/clear-settings`, `POST /admin/clear-settings/test-upstream` | `manage_clear_settings` |
| Bank import | `POST /admin/bank-import-batches` (CSV), `GET` list, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Bank transactions | `GET /admin/bank-transactions` (filters), `GET /{id}`, `GET /unmatched` | `view_reconciliation` |
| Invoice upstream | `GET /admin/upstream/invoices` (read-only proxy) | `view_reconciliation` |
| Reconciliation | `POST /admin/reconciliations/propose`, `POST /admin/reconciliations` (confirm), `GET` list/by-id, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Client credits | `GET /admin/client-credits`, `POST /{id}/apply` | `view_reconciliation` / `manage_reconciliation` |
| Dunning | `POST /admin/dunning-notices`, `GET` list/by-id | `send_dunning` / `view_reconciliation` |
| Dunning pause | `GET/POST /admin/dunning-pauses`, `POST /{invoiceId}/resume` | `send_dunning` / `view_reconciliation` |
| Export (CSV) | `GET /admin/export/{reconciliations,client-credits,bank-transactions}` | `view_reconciliation` |
| Audit log | `GET /admin/audit-events` | `manage_users` |
| Admin UI | React SPA (`frontend/`), served from `public_html/assets/` | JWT (session storage) |

## Infrastructure

Fixed local ports (see `CLAUDE.md`; do **not** revert to framework defaults).

| Component | Status | Notes |
| --- | --- | --- |
| Docker | ✅ `docker-compose.yml` | MySQL 8.4 (host port 3383) + Mailpit (SMTP 1383, web UI 8383) |
| PHP backend | ✅ | `NENE_CLEAR_PORT=8384` |
| Vite dev server | ✅ | `NENE_CLEAR_FRONTEND_PORT=5383` |
| SMTP mailer | ✅ `SmtpDunningMailer` | Activated when `SMTP_HOST` set; falls back to `LogOnlyDunningMailer`; credentials passed via EsmtpTransport (not DSN) |
| Invoice upstream | ✅ `InvoiceUpstreamHttpClient` | Activated when `NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` set; falls back to `FakeInvoiceUpstreamClient` |
| Login throttle | ✅ `PdoLoginThrottle` | 5 failures / 15 min per email+IP → 15 min lock (HTTP 429) |
| Contract tests | ✅ 6 tests (auto-skip) | Run against real Invoice API by setting env vars |
| CI | ✅ GitHub Actions | backend / frontend / e2e / close-issues workflows |

## Open risks

1. **Real upstream connection not yet exercised** — `InvoiceUpstreamHttpClient` +
   contract tests are ready; set `NENE_INVOICE_API_BASE_URL` /
   `NENE_INVOICE_BEARER_TOKEN` against a live Invoice instance and run the
   contract suite to confirm the integration end-to-end.

## Next steps

**Adoption Readiness milestone — nearly complete**
([`../milestones/2026-06-adoption-readiness.md`](../milestones/2026-06-adoption-readiness.md);
from the 2026-06 adoption review #190,
[`../explanation/adoption-review-2026-06.md`](../explanation/adoption-review-2026-06.md)).
Shipped: ✅ UI polish (#196, PR #200) · ✅ account-number masking (#192, PR #201) ·
✅ standalone receivables — CTA / import aliases / repositioning (#191, PR #202–#204) ·
✅ dunning hardening — deliverability / preview / test-send / staged templates
(#194, PR #205/#208/#209/#210) · ✅ encryption-at-rest (#195, PR #207) ·
✅ retract Tier A (#193, PR #206).

**The one remaining milestone item — MFA (#195):**

1. **Standalone TOTP MFA** — auth is federated (Suite = IdP) and MFA/TOTP is a
   NENE2-generic capability (nene-suite#341, ADR 0025), so Clear builds standalone
   TOTP now (NENE2 `totp-authentication` recipe). enroll = user / enforce =
   deployment operator policy; mandatory recovery codes; secret encrypted via the
   #207 `Encryptor`; break-glass via an audited CLI. Sequence:
   (a) TOTP foundation (no login-flow change) → (b) login-flow integration →
   (c) enroll UI + admin/CLI reset.

**Minor follow-ups** (open on their issues): real dashboard KPI-sub aggregates +
term tooltips (#196); server-side account-number withholding (#192); column-mapping
UI (#191); dunning stage persistence + ADR 0011 tone review (#194).

**Then resume prior roadmap:**
2. **Activate real Invoice upstream** — set env vars, run contract tests live.
3. **CSV export — tax advisor sign-off** — review column set per compliance §9.
4. **Phase 4 — Ecosystem** — MCP tools (`listUnmatchedTransactions`,
   `proposeMatch`, `sendDunningNotice`).

Business/owner levers (not code; see review doc): managed/SaaS supply, pricing
(A/B/C), tax-advisor MSP channel. Roadmap **Phase 3** was reframed to
"Distribution (managed / install-service)" per #193 (PR #206).

## Recently completed

- 2026-06 adoption review (10-persona simulation): findings doc
  [`../explanation/adoption-review-2026-06.md`](../explanation/adoption-review-2026-06.md)
  (#190) + follow-up issues #191–#196, scheduled in the Adoption Readiness milestone.
- Dev tooling: one-command `/dev-up` via `.claude/skills/run-local/run-local.sh`
  (php -S + SQLite + Vite + Mailpit); public-repo wording fixes.
- Post-Phase-2 hardening merged to main (PR #99–#118): see Status above.
- i18n message catalog (ja/en), multi-tenant SQL scoping, audit before/after
  snapshots, reconciliation idempotency, operator guide (電子帳簿保存法 §3.4).
- Admin UI (login, dashboard, bank import, transactions, reconciliation,
  dunning, client credits, settings, users, audit log) with full test coverage.
- Security hardening: login rate limiting, role-assignment privilege-escalation
  guard, SMTP credential handling, upstream error masking.
- CI pipelines (backend / frontend / e2e / auto-close-issues).
