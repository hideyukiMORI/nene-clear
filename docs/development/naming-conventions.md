# Naming Conventions

Authoritative naming rules for NeNe Clear code, API contracts, database objects, tests, and English documentation.

> **Absolute adherence — non-negotiable.** These rules are **MUST**, not
> suggestions. A name that violates a rule here, or a typo / spelling variant of
> a registered term, is a defect and **blocks merge**. There is no "close
> enough." When in doubt, match the registry exactly.
>
> The concrete canonical spelling of every term and identifier lives in the
> **single source of truth**: [`../explanation/terminology.md`](../explanation/terminology.md).
> This document defines the *patterns*; the registry defines the *exact strings*.
> Introducing or renaming any identifier **MUST** update the registry in the same PR.

**Terminology registry (canonical spellings):** [`docs/explanation/terminology.md`](../explanation/terminology.md)
**Glossary (product term meanings):** [`docs/explanation/glossary.md`](../explanation/glossary.md)

**Framework baseline:** NENE2 [`domain-layer.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/domain-layer.md) and [`database-migrations.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/database-migrations.md). This document is the NeNe Clear override and extension list.

---

## 1. PHP

### Files and namespaces

| Item | Rule | Example |
| --- | --- | --- |
| Namespace root | `NeneClear\` | `NeneClear\Invoice\CreateInvoiceHandler` |
| Domain folder | PascalCase singular domain name | `src/Client/`, `src/Invoice/` |
| File name | Match the primary class | `CreateInvoiceHandler.php` |
| One public class per file | Required | — |

### Classes and interfaces

| Role | Pattern | Example |
| --- | --- | --- |
| HTTP handler | `{Verb}{Noun}Handler` | `CreateInvoiceHandler`, `ListClientsHandler` |
| Use case interface | `{Verb}{Noun}UseCaseInterface` | `CreateInvoiceUseCaseInterface` |
| Use case impl | `{Verb}{Noun}UseCase` | `CreateInvoiceUseCase` |
| Use case method | Always `execute` | `execute(CreateInvoiceInput $input): CreateInvoiceOutput` |
| Input DTO | `{Verb}{Noun}Input` | `CreateInvoiceInput` |
| Output DTO | `{Verb}{Noun}Output` | `CreateInvoiceOutput` |
| Domain entity | Singular noun, no suffix | `Client`, `Quote`, `Invoice`, `Payment` |
| Repository interface | `{Entity}RepositoryInterface` | `InvoiceRepositoryInterface` |
| PDO repository | `Pdo{Entity}Repository` | `PdoInvoiceRepository` |
| PDF adapter | `{Purpose}PdfGenerator` in `Pdf/` | `InvoicePdfGenerator` |
| Domain exception | `{Entity}{Reason}Exception` | `InvoiceNotFoundException` |
| Service provider | `{Purpose}ServiceProvider` | `RuntimeServiceProvider` |

All application classes: `final` and `readonly` where applicable. Every PHP file: `declare(strict_types=1);`.

### Modules (`src/`)

Use only domain-grouped top-level folders. Do not add layer folders (`Handlers/`, `Repositories/`, `UseCases/`).

Planned domains (Phase 1+): `Organization/`, `Auth/`, `User/`, `Client/`, `Quote/`, `Invoice/`, `Payment/`, `Company/`, `LineItem/`, `Pdf/`, `Http/`.

### Methods and properties

| Item | Rule | Example |
| --- | --- | --- |
| Methods | camelCase | `findById`, `markAsPaid` |
| Properties | camelCase | `$clientId`, `$invoiceRepository` |
| Constants | UPPER_SNAKE_CASE | `MAX_LINE_ITEMS` |

Repository methods use **domain verbs**: `findById`, `save`, `delete` — not `selectById`, `insertRow`.

---

## 2. HTTP routes and OpenAPI

### URL paths

| Item | Rule | Example |
| --- | --- | --- |
| Path segments | lowercase **kebab-case** | `/admin/clients`, `/admin/invoices` |
| Collection paths | plural noun | `/admin/quotes`, `/admin/payments` |
| Single resource | `{id}` path param | `/admin/invoices/{id}` |
| Public download | noun path | `/documents/invoices/{token}/pdf` |
| Path param name | lowercase singular | `id`, `clientId` |

Admin mutating routes live under `/admin/…`.

### operationId

| Item | Rule | Example |
| --- | --- | --- |
| Case | camelCase | `getHealth`, `createInvoice` |
| Shape | `{verb}{Resource}` or `{verb}{Resource}ById` | `listClients`, `getInvoiceById` |
| Stability | Never rename after release; deprecate instead | — |

Must match between `docs/openapi/openapi.yaml`, route registration, and `docs/mcp/tools.json` `operationId`.

### OpenAPI schema names

| Item | Rule | Example |
| --- | --- | --- |
| Response schema | `{Resource}Response` | `InvoiceResponse` |
| List response | `{Resource}ListResponse` | `ClientListResponse` |
| Create request | `Create{Resource}Request` | `CreateQuoteRequest` |
| Tag names | PascalCase singular group | `System`, `Admin`, `Client`, `Invoice` |

Public OpenAPI summaries, descriptions, and examples: **English only**.

---

## 3. JSON (request and response bodies)

| Item | Rule | Example |
| --- | --- | --- |
| Property names | **snake_case** | `client_id`, `issued_at`, `tax_rate` |
| Money amounts | integer **cents** | `subtotal_cents`, `tax_cents`, `total_cents` |
| Booleans | `is_` / `has_` prefix | `is_paid`, `is_qualified_invoice` |
| Timestamps | `_at` suffix, ISO 8601 string | `issued_at`, `due_at`, `paid_at` |
| Foreign keys | `{entity}_id` | `client_id`, `quote_id` |
| List envelope | `items`, `limit`, `offset` | Same as NENE2 list pattern |

Do not mix camelCase in public JSON. Do not use floats for money.

---

## 4. Problem Details and validation errors

| Item | Rule | Example |
| --- | --- | --- |
| Base URL | `https://nene-clear.dev/problems/` | — |
| Type slug | kebab-case | `validation-failed`, `invoice-not-found` |
| Validation `errors[].field` | snake_case path | `body.registration_number` |
| Validation `errors[].code` | snake_case | `required`, `invalid_invoice_number` |

Problem Details `title` and `detail`: English.

---

## 5. Database

| Item | Rule | Example |
| --- | --- | --- |
| Table names | snake_case, **plural** | `clients`, `quotes`, `invoices`, `line_items`, `payments` |
| Column names | snake_case | `client_id`, `total_cents`, `issued_at` |
| Money columns | `*_cents` suffix, integer | `subtotal_cents`, `tax_cents` |
| Primary key | `id` | BIGINT auto-increment |
| Foreign key column | `{singular_entity}_id` | `quote_id`, `invoice_id` |
| Index names | `idx_{table}_{columns}` | `idx_invoices_client_id` |
| Unique constraints | `uniq_{table}_{columns}` | `uniq_invoices_number` |

SQL lives only in `Pdo*Repository` classes.

### Migrations

| Item | Rule | Example |
| --- | --- | --- |
| File name | `YYYYMMDDHHMMSS_snake_description.php` | `20260529120000_create_invoices_table.php` |
| Snapshot file | `database/schema/{table}.sql` | `database/schema/invoices.sql` |

---

## 6. Environment variables

| Item | Rule | Example |
| --- | --- | --- |
| Names | UPPER_SNAKE_CASE | `DB_HOST`, `NENE_CLEAR_PORT` |
| Prefix | Product-specific compose overrides | `NENE_CLEAR_` |
| Secrets | Never commit; document in `.env.example` only | — |

---

## 7. Tests

| Item | Rule | Example |
| --- | --- | --- |
| Test class | `{ClassUnderTest}Test` | `CreateInvoiceUseCaseTest` |
| Test method | `test_{behavior}_when_{condition}` | `test_rejects_missing_registration_number_when_qualified_invoice` |
| Test namespace | Mirror `src/` under `tests/` | `tests/Invoice/CreateInvoiceUseCaseTest.php` |

---

## 8. MCP tools

| Item | Rule | Example |
| --- | --- | --- |
| Tool `name` | Same as OpenAPI `operationId` | `listInvoices` |
| Tool `title` | Short English Title Case | `List Invoices` |
| `safety` | `read` or `write` | Prefer `read` until auth review passes |

Catalog: `docs/mcp/tools.json`. Validate with `composer mcp`.

---

## 9. Frontend (Phase 2+)

| Item | Rule |
| --- | --- |
| Components | PascalCase file and export |
| Hooks | camelCase with `use` prefix |
| API client | Maps snake_case JSON; do not rename API fields in transit |
| Admin SPA | React + TypeScript strict mode |

Full frontend standards: **`docs/development/frontend-standards.md`** (Phase 2).

---

## 10. Documentation and commits

| Surface | Language | Naming |
| --- | --- | --- |
| Public docs, OpenAPI, API errors | English | Use glossary canonical terms |
| Issues, PRs, commit bodies | Japanese allowed | Prefer glossary English term on first mention |
| Commit subject | Conventional Commits + `(#issue)` | See [`commit-conventions.md`](./commit-conventions.md) |
| ADR file | `NNNN-kebab-title.md` | `0002-separate-from-sibling-products.md` |

When adding or renaming any identifier, update [`terminology.md`](../explanation/terminology.md) in the same PR; if it is a product concept, also update [`glossary.md`](../explanation/glossary.md).

---

## 11. Prohibited patterns

- **Typos or spelling variants of any term registered in `terminology.md`** (e.g. `tax_registration_number` for `registration_number`, `sub_total_cents` for `subtotal_cents`) — blocks merge
- **Unregistered identifiers** — using an entity, status, field, slug, or `operationId` not present in `terminology.md` without adding it in the same PR
- Layer-first folders (`src/Handlers/`, `src/Repositories/`)
- SQL outside `Pdo*Repository`
- camelCase in public JSON property names
- Float or DECIMAL for money in SQLite tests or API JSON
- Renaming shipped `operationId` values
- Embedding billing logic in NeNe Records or other sibling repos

---

## Verification

```bash
composer check
composer openapi
composer mcp
```

Review checklists: [`docs/review/`](../review/).
