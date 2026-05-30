# Current Work

Last updated: 2026-05-31

## Status

**Phase 1 — Complete.**
**Phase 2 — Complete (dunning functional; professional review gate not yet obtained).**
**Infrastructure — Complete (docker-compose + Mailpit; SmtpDunningMailer; InvoiceUpstreamHttpClient).**

126 tests (5 skipped) / 473 assertions, PHPStan level 8 clean.
5 skipped = contract tests that auto-activate once `NENE_INVOICE_API_BASE_URL` is set.

## What is running

| Domain | Endpoints | Auth |
| --- | --- | --- |
| Auth | `POST /admin/auth/login`, `GET /admin/auth/me` | JWT |
| Organization | CRUD `/admin/organizations` | `manage_organizations` |
| User | CRUD `/admin/users` | `manage_users` |
| ClearSettings | `GET/PUT /admin/clear-settings`, `POST /admin/clear-settings/test-upstream` | `manage_clear_settings` |
| Bank import | `POST /admin/bank-import-batches` (CSV), `GET` list, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Bank transactions | `GET /admin/bank-transactions` (filters), `GET /{id}`, `GET /unmatched` | `view_reconciliation` |
| Reconciliation | `POST /propose`, `POST /confirm`, `GET` list/by-id, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Client credits | `GET /admin/client-credits`, `POST /{id}/apply` | `view_reconciliation` / `manage_reconciliation` |
| Dunning | `POST /admin/dunning-notices`, `GET` list/by-id | `send_dunning` / `view_reconciliation` |

## Infrastructure

| Component | Status | Notes |
| --- | --- | --- |
| Docker | ✅ `docker-compose.yml` | MySQL 8.4 (port 3310) + Mailpit (SMTP 1025, web UI 8025) |
| SMTP mailer | ✅ `SmtpDunningMailer` | Activated when `SMTP_HOST` env var is set; falls back to `LogOnlyDunningMailer` |
| Invoice upstream | ✅ `InvoiceUpstreamHttpClient` | Activated when `NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` are set; falls back to `FakeInvoiceUpstreamClient` |
| Contract tests | ✅ 5 tests (auto-skip) | Run against real Invoice API by setting env vars |

## Open risks

1. **nene-invoice API not built** — `InvoiceUpstreamHttpClient` is ready but targets endpoints that don't exist yet. See [`docs/integrations/invoice-upstream-http-client-requirements.md`](../integrations/invoice-upstream-http-client-requirements.md) for the full checklist to hand to the Invoice team.
2. **Professional sign-off not obtained** — 税理士/公認会計士 (reconciliation) + 弁護士 (dunning / 弁護士法72条) review gate required before shipping. Compliance §9.

## Next steps

1. **nene-invoice team** — implement R1–R3, W1–W2, A1–A5 per [`invoice-upstream-http-client-requirements.md`](../integrations/invoice-upstream-http-client-requirements.md); then set env vars in nene-clear to activate real client.
2. **Professional review gate** — obtain sign-off (human action).
3. **Admin UI** — frontend for reconciliation + dunning workflow.
4. **Dunning template customization** — operator-editable templates per org (currently hardcoded).
