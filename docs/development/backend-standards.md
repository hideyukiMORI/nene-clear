# Backend Standards

NeNe Clear backend policy for PHP API code. Adapted from [NeNe Records](https://github.com/hideyukiMORI/nene-records) and [NeNe Corpus](https://github.com/hideyukiMORI/nene-corpus) backend standards for a **billing OSS** on NENE2.

**Framework baseline:** NENE2 `docs/development/` — deviate only via local ADR.

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
  Company/          # issuer profile, tax registration, bank info (per organization)
  Client/           # customer master
  Quote/            # estimates
  Invoice/          # issued invoices, qualified invoice fields
  LineItem/         # shared line item logic (quote + invoice)
  Payment/          # payment records, status
  Pdf/              # server-side PDF generation
  Upstream/         # optional HTTP clients (Records, Concierge)
```

Every tenant-scoped table and query carries `organization_id` (ADR 0006). Only
superadmin operates cross-tenant.

**Zero-tolerance placement:** handlers live in their domain folder (`Invoice/CreateInvoiceHandler.php`), not `src/Handlers/`.

---

## 3. Layering rules

```
Handler → UseCase → RepositoryInterface → PdoRepository
```

| Layer | May | Must not |
| --- | --- | --- |
| **Handler** | Parse HTTP, build DTO, call UseCase, map JSON response | SQL, business rules, direct PDF library calls |
| **UseCase** | Business rules, tax calculation, orchestration | `$_SERVER`, PDO, raw HTTP |
| **Repository** | SQL / persistence | HTTP, PDF generation |
| **Pdf adapter** | Render PDF from DTO | Domain invariants, SQL |

Use `final readonly` classes and `declare(strict_types=1);` in every PHP file.

---

## 4. HTTP & OpenAPI

- Every public route appears in `docs/openapi/openapi.yaml` with `operationId`.
- Success and Problem Details error shapes documented.
- RFC 9457 Problem Details for errors; base URL `https://nene-clear.dev/problems/`.
- Admin routes require JWT Bearer auth (Phase 1+).

---

## 5. Money and tax

> **Compliance is binding (non-negotiable).** All billing, tax, numbering, PDF,
> and retention behavior **MUST** comply with
> [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md).
> Where compliance conflicts with convenience, compliance wins. Deviations
> require an ADR with tax-professional sign-off. Run
> [`../review/compliance.md`](../review/compliance.md) for any change in this area.

- Store all amounts as **integer cents** (`subtotal_cents`, `tax_cents`, `total_cents`). Float / DECIMAL for money is prohibited.
- Tax rates: store as basis points or documented enum — never float percentages in persisted data.
- Consumption tax is rounded **once per tax rate per document**, half-up — never per line (ADR 0004).
- Japan qualified invoice fields validated in UseCase layer before PDF generation; missing required fields block issuance.
- Issued documents are immutable; corrections via credit note, not edit/delete. No hard delete of billing records.
- PDF totals must match API-calculated cents — single source of truth in UseCase.

---

## 6. Database

- Phinx migrations under `database/migrations/`.
- Schema snapshots under `database/schema/`.
- Soft delete: `is_deleted`, `deleted_at` unless ADR says otherwise.
- Multi-tenant: `organization_id` on tenant-scoped tables when tenancy lands (ADR TBD).

---

## 7. Testing

- UseCase tests: no DB — inject repository fakes or in-memory implementations.
- Repository tests: SQLite in-memory PDO.
- HTTP tests: contract tests against OpenAPI shapes.
- PDF tests: assert byte output or snapshot hash — do not visually review in CI.

---

## 8. Verification

```bash
composer check
composer openapi
composer mcp
```

Self-review: [`docs/review/backend-api.md`](../review/backend-api.md), [`docs/review/database.md`](../review/database.md), [`docs/review/compliance.md`](../review/compliance.md).
