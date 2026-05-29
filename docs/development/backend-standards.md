# Backend Standards

NeNe Clear backend policy for PHP API code. Adapted from [NeNe Records](https://github.com/hideyukiMORI/nene-records) and [NeNe Corpus](https://github.com/hideyukiMORI/nene-corpus) backend standards for a **payment reconciliation & dunning OSS** on NENE2.

**Framework baseline (binding):** NeNe Clear MUST follow NENE2 conventions per
[`nene2-compliance.md`](./nene2-compliance.md) — the concrete framework symbols
to reuse (`JsonResponseFactory`, `ProblemDetailsResponseFactory`, `Router`,
`PaginationQuery`/`PaginationResponse`, `RequestScopedHolder`,
`BearerTokenMiddleware`, `DatabaseQueryExecutorInterface`, `ServiceProviderInterface`,
`ValidationError`/`ValidationException`, …), the Handler→UseCase→Repository flow,
DI wiring, validation layering, errors, auth/tenancy, DB, and OpenAPI. Deviate
from NENE2 framework behavior only via a local ADR.

**Naming and terms:** [`naming-conventions.md`](./naming-conventions.md), [`glossary.md`](../explanation/glossary.md).

---

## 1. Project shape

NeNe Clear is a **NENE2 consumer project**:

```
vendor/hideyukimori/nene2/   ← framework (do not edit)
src/                         ← product code (NeneClear\)
tests/                       ← mirrors src/
docs/openapi/openapi.yaml    ← public contract
public_html/index.php        ← front controller
```

Namespace: `NeneClear\`

---

## 2. Module layout (domain-grouped)

Organize by **domain**, not technical layer:

```
src/
  ApplicationServiceProvider.php
  Http/
  Organization/     # tenants + per-request resolution (Organization/Resolution/)
  Auth/             # JWT login, Role / Capability, capability middleware
  User/             # operator accounts within an organization
  Settings/         # ClearSettings — upstream URL/token, bank accounts, dunning defaults (per organization)
  BankImport/       # CSV import, bank_import_batches, bank_transactions, duplicate detection
  Reconciliation/   # match proposal, confirmation, allocation, reversal
  ClientCredit/     # overpayment balances and application
  Dunning/          # templates, send, send history
  Upstream/         # Invoice API client (read invoices/clients, write payments); optional Records/Concierge
  Audit/            # immutable audit events
```

Every tenant-scoped table and query carries `organization_id` (ADR 0006). Only
superadmin operates cross-tenant.

**Zero-tolerance placement:** handlers live in their domain folder (`Reconciliation/ConfirmMatchHandler.php`), not `src/Handlers/`.

---

## 3. Layering rules

```
Handler → UseCase → RepositoryInterface → PdoRepository
```

| Layer | May | Must not |
| --- | --- | --- |
| **Handler** | Parse HTTP, build DTO, call UseCase, map JSON response | SQL, business rules, direct upstream HTTP calls |
| **UseCase** | Business rules, allocation logic, dunning eligibility, orchestration | `$_SERVER`, PDO, raw HTTP |
| **Repository** | SQL / persistence | HTTP, business rules |
| **Upstream client** | HTTP calls to the Invoice API behind an interface | Domain invariants, SQL |

Use `final readonly` classes and `declare(strict_types=1);` in every PHP file.

---

## 4. HTTP & OpenAPI

- Every public route appears in `docs/openapi/openapi.yaml` with `operationId`.
- Success and Problem Details error shapes documented.
- RFC 9457 Problem Details for errors; base URL `https://nene-clear.dev/problems/`.
- Admin routes require JWT Bearer auth (Phase 1+).

---

## 5. Money and reconciliation

> **Compliance is binding (non-negotiable).** All bank import, reconciliation,
> client-credit, dunning, retention, and audit behavior **MUST** comply with
> [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md)
> (authoritative) and [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md).
> Where compliance conflicts with convenience, compliance wins. Deviations
> require an ADR with tax-professional sign-off. Run
> [`../review/compliance.md`](../review/compliance.md) for any change in this area.

- Store all amounts as **integer cents** (`amount_cents`, `remaining_cents`, `outstanding_at_send_cents`). Float / DECIMAL for money is prohibited.
- **No quote/invoice/tax/PDF logic** (ADR 0009). Invoice totals and tax are read from the Invoice upstream; Clear allocates known bank amounts and never recomputes them.
- Allocation math is computed once in the UseCase; API responses and stored rows render the same values.
- Imported bank lines are immutable; corrections via reversal import batch, not in-place edit. No hard delete of bank/match/dunning history.
- `paid_at` written upstream uses the bank value date (`value_date`), not the confirm date.

---

## 6. Database

- Phinx migrations under `database/migrations/`.
- Schema snapshots under `database/schema/`.
- Soft delete: `is_deleted`, `deleted_at` unless ADR says otherwise.
- Multi-tenant from the foundation: `organization_id` on every tenant-scoped table (ADR 0006).

---

## 7. Testing

- UseCase tests: no DB — inject repository fakes or in-memory implementations.
- Repository tests: SQLite in-memory PDO.
- HTTP tests: contract tests against OpenAPI shapes.
- Upstream client tests: fake the Invoice API behind the client interface; cover degraded mode (`upstream-invoice-unavailable`).
- Reconciliation tests: cover partial payment, overpayment → client credit, transfer-fee mismatch, and reversal.

---

## 8. Verification

```bash
composer check
composer openapi
composer mcp
```

Self-review: [`docs/review/backend-api.md`](../review/backend-api.md), [`docs/review/database.md`](../review/database.md), [`docs/review/compliance.md`](../review/compliance.md).
