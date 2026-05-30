# Current Work

Last updated: 2026-05-31

## Status

**Phase 1 — Complete.**

All endpoints defined in `docs/openapi/openapi.yaml` are implemented and tested (99 tests / 382 assertions, PHPStan level 8 clean).

**Phase 2 — Not started.** Blocked on professional review gate (see §Open risks).

## What is running

| Domain | Endpoints | Auth |
| --- | --- | --- |
| Auth | `POST /admin/auth/login`, `GET /admin/auth/me` | JWT / BearerTokenMiddleware |
| Organization | CRUD `/admin/organizations` | `manage_organizations` |
| User | CRUD `/admin/users` | `manage_users` |
| Bank import | `POST /admin/bank-import-batches` (CSV upload), `GET` list, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Bank transactions | `GET /admin/bank-transactions` (filters), `GET /{id}`, `GET /unmatched` | `view_reconciliation` |
| Reconciliation | `POST /propose`, `POST /confirm`, `GET` list/by-id, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Client credits | `GET /admin/client-credits`, `POST /{id}/apply` | `view_reconciliation` / `manage_reconciliation` |

## Domain split (binding)

| Product | Repository | Domain |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` (public) | Quote, invoice, payment management — 見積・請求・入金管理 |
| **NeNe Clear** | `nene-clear` (this) | Payment reconciliation & dunning — 入金消込・督促管理 |

**Not upper compatible.** See [`docs/adr/0009-separate-from-nene-invoice.md`](../adr/0009-separate-from-nene-invoice.md).

## Open risks

- **Invoice upstream HTTP client** — all Invoice calls go to `FakeInvoiceUpstreamClient`. A real `InvoiceUpstreamHttpClient` is needed once `nene-invoice` builds `POST /api/invoices/{id}/payments` (and the read API). See [`docs/integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md).
- **Professional sign-off not obtained** — 税理士/公認会計士 (accounting/tax) + 弁護士 (dunning / 弁護士法72条) review gate required before Phase 1 ships and before Phase 2 starts. See compliance §9.

## Next steps

1. **Invoice upstream HTTP client** — implement `InvoiceUpstreamHttpClient` once `nene-invoice` API is live; swap into `ApplicationFactory`.
2. **Professional review gate** — obtain sign-off (human action, not engineering).
3. **Phase 2 — Dunning** — after gate: scheduled dunning escalation, `send_dunning` capability, 弁護士法72条 compliance wiring.
4. **Admin UI** — Phase 2 alongside dunning.
5. **Consolidated code review** — recommended for B-6 slices (reconciliation domain + ConfirmMatchUseCase).
