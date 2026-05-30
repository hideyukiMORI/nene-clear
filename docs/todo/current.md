# Current Work

Last updated: 2026-05-31

## Status

**Phase 1 — Complete.**
**Phase 2 — Complete (dunning functional; professional review gate not yet obtained).**

All implemented endpoints: 121 tests / 473 assertions, PHPStan level 8 clean.

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

## Domain split (binding)

| Product | Repository | Domain |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` (public) | Quote, invoice, payment management |
| **NeNe Clear** | `nene-clear` (this) | Payment reconciliation & dunning |

## Open risks

1. **Invoice upstream HTTP client** — all Invoice calls use `FakeInvoiceUpstreamClient`. Real `InvoiceUpstreamHttpClient` needed once `nene-invoice` builds the API. See [`docs/integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md).
2. **SMTP email delivery** — dunning uses `LogOnlyDunningMailer` (logs only). Real sending requires SMTP config in `ClearSettings` + `SmtpDunningMailer`.
3. **Professional sign-off not obtained** — 税理士/公認会計士 (reconciliation) + 弁護士 (dunning / 弁護士法72条) review gate required before shipping to production. Compliance §9.

## Next steps

1. **Invoice upstream HTTP client** — implement `InvoiceUpstreamHttpClient` once `nene-invoice` API is live.
2. **SMTP dunning mailer** — add SMTP fields to `ClearSettings`; implement `SmtpDunningMailer`.
3. **Professional review gate** — obtain sign-off (human action).
4. **Admin UI** — frontend for reconciliation + dunning workflow.
5. **Dunning template customization** — operator-editable templates per org.
