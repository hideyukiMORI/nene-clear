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

**Framework baseline:** NENE2 [`domain-layer.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/domain-layer.md) and [`database-migrations.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/database-migrations.md), mapped to binding Clear rules in [`nene2-compliance.md`](./nene2-compliance.md) (§4 naming, §7 framework objects). This document is the NeNe Clear override and extension list.

---

## 1. PHP

### Files and namespaces

| Item | Rule | Example |
| --- | --- | --- |
| Namespace root | `NeneClear\` | `NeneClear\Reconciliation\ConfirmMatchHandler` |
| Domain folder | PascalCase singular domain name | `src/Reconciliation/`, `src/Dunning/` |
| File name | Match the primary class | `ConfirmMatchHandler.php` |
| One public class per file | Required | — |

### Classes and interfaces

| Role | Pattern | Example |
| --- | --- | --- |
| HTTP handler | `{Verb}{Noun}Handler` | `ConfirmMatchHandler`, `ListBankTransactionsHandler` |
| Use case interface | `{Verb}{Noun}UseCaseInterface` | `ConfirmMatchUseCaseInterface` |
| Use case impl | `{Verb}{Noun}UseCase` | `ConfirmMatchUseCase` |
| Use case method | Always `execute` | `execute(ConfirmMatchInput $input): ConfirmMatchOutput` |
| Input DTO | `{Verb}{Noun}Input` | `ConfirmMatchInput` |
| Output DTO | `{Verb}{Noun}Output` | `ConfirmMatchOutput` |
| Domain entity | Singular noun, no suffix | `BankTransaction`, `PaymentReconciliation`, `DunningNotice` |
| Repository interface | `{Entity}RepositoryInterface` | `BankTransactionRepositoryInterface` |
| PDO repository | `Pdo{Entity}Repository` | `PdoBankTransactionRepository` |
| Upstream client | `{Purpose}ClientInterface` + `Http{Purpose}Client` in `Upstream/` | `InvoiceUpstreamClientInterface`, `HttpInvoiceUpstreamClient` |
| Domain exception | `{Entity}{Reason}Exception` | `BankTransactionNotFoundException` |
| Service provider | `{Purpose}ServiceProvider` | `RuntimeServiceProvider` |

All application classes: `final` and `readonly` where applicable. Every PHP file: `declare(strict_types=1);`.

### Modules (`src/`)

Use only domain-grouped top-level folders. Do not add layer folders (`Handlers/`, `Repositories/`, `UseCases/`).

Domain folders (`src/`): `Organization/`, `Auth/`, `User/`, `ClearSettings/`, `BankImport/`, `Reconciliation/` (includes client credit), `Dunning/`, `InvoiceUpstream/`, `Audit/`, `I18n/`, `Http/`.

### Methods and properties

| Item | Rule | Example |
| --- | --- | --- |
| Methods | camelCase | `findById`, `markAsMatched` |
| Properties | camelCase | `$bankTransactionId`, `$reconciliationRepository` |
| Constants | UPPER_SNAKE_CASE | `DEFAULT_DUNNING_INTERVAL_DAYS` |

Repository methods use **domain verbs**: `findById`, `save`, `delete` — not `selectById`, `insertRow`.

---

## 2. HTTP routes and OpenAPI

### URL paths

| Item | Rule | Example |
| --- | --- | --- |
| Path segments | lowercase **kebab-case** | `/admin/bank-transactions`, `/admin/reconciliations` |
| Collection paths | plural noun | `/admin/bank-import-batches`, `/admin/dunning-notices` |
| Single resource | `{id}` path param | `/admin/bank-transactions/{id}` |
| Upstream read proxy | noun path | `/admin/upstream/invoices` |
| Path param name | lowercase singular | `id`, `bankTransactionId` |

Admin mutating routes live under `/admin/…`.

### operationId

| Item | Rule | Example |
| --- | --- | --- |
| Case | camelCase | `getHealth`, `confirmMatch` |
| Shape | `{verb}{Resource}` or `{verb}{Resource}ById` | `listBankTransactions`, `getReconciliationById` |
| Stability | Never rename after release; deprecate instead | — |

Must match between `docs/openapi/openapi.yaml`, route registration, and `docs/mcp/tools.json` `operationId`.

### OpenAPI schema names

| Item | Rule | Example |
| --- | --- | --- |
| Response schema | `{Resource}Response` | `BankTransactionResponse` |
| List response | `{Resource}ListResponse` | `DunningNoticeListResponse` |
| Create request | `Create{Resource}Request` | `ConfirmMatchRequest` |
| Tag names | PascalCase singular group | `System`, `Admin`, `Reconciliation`, `Dunning` |

Public OpenAPI summaries, descriptions, and examples: **English only**.

---

## 3. JSON (request and response bodies)

| Item | Rule | Example |
| --- | --- | --- |
| Property names | **snake_case** | `bank_transaction_id`, `confirmed_at`, `counterparty_text` |
| Money amounts | integer **cents** | `amount_cents`, `outstanding_at_send_cents`, `remaining_cents` |
| Booleans | `is_` / `has_` prefix | `is_deleted`, `has_remainder` |
| Timestamps | `_at` suffix, ISO 8601 string | `imported_at`, `confirmed_at`, `sent_at` |
| Calendar dates | `_date` suffix (documented exception: `value_date`) | `value_date` |
| Hour of day (a setting, not an instant) | `_hour` suffix, integer 0–23 | `dunning_window_start_hour`, `dunning_window_end_hour` |
| Foreign keys | `{entity}_id` | `bank_transaction_id`, `invoice_id` (upstream) |
| List envelope | `items`, `limit`, `offset` | Same as NENE2 list pattern |

Do not mix camelCase in public JSON. Do not use floats for money.

---

## 4. Problem Details and validation errors

| Item | Rule | Example |
| --- | --- | --- |
| Base URL | `https://nene-clear.dev/problems/` | — |
| Type slug | kebab-case | `validation-failed`, `bank-transaction-not-found` |
| Validation `errors[].field` | snake_case path | `body.value_date` |
| Validation `errors[].code` | snake_case | `required`, `invalid_amount` |

Problem Details `title` and `detail`: English.

---

## 5. Database

| Item | Rule | Example |
| --- | --- | --- |
| Table names | snake_case, **plural** | `bank_transactions`, `bank_import_batches`, `payment_reconciliations`, `dunning_notices` |
| Column names | snake_case | `bank_transaction_id`, `amount_cents`, `confirmed_at` |
| Money columns | `*_cents` suffix, integer | `amount_cents`, `remaining_cents` |
| Primary key | `id` | BIGINT auto-increment |
| Foreign key column | `{singular_entity}_id` | `bank_import_batch_id`, `invoice_id` (upstream) |
| Index names | `idx_{table}_{columns}` | `idx_bank_transactions_status` |
| Unique constraints | `uniq_{table}_{columns}` | `uniq_bank_import_batches_file_hash` |

SQL lives only in `Pdo*Repository` classes.

### Migrations

| Item | Rule | Example |
| --- | --- | --- |
| File name | `YYYYMMDDHHMMSS_snake_description.php` | `20260529120000_create_bank_transactions_table.php` |
| Snapshot file | `database/schema/{table}.sql` | `database/schema/bank_transactions.sql` |

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
| Test class | `{ClassUnderTest}Test` | `ConfirmMatchUseCaseTest` |
| Test method | `test_{behavior}_when_{condition}` | `test_records_client_credit_when_allocation_exceeds_outstanding` |
| Test namespace | Mirror `src/` under `tests/` | `tests/Reconciliation/ConfirmMatchUseCaseTest.php` |

---

## 8. MCP tools

| Item | Rule | Example |
| --- | --- | --- |
| Tool `name` | Same as OpenAPI `operationId` | `listUnmatchedTransactions` |
| Tool `title` | Short English Title Case | `List Unmatched Transactions` |
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
| Issues, PRs, commit bodies | English only ([ADR 0008](../adr/0008-english-only-repository-documentation.md)) | Use glossary English term on first mention |
| Commit subject | Conventional Commits + `(#issue)` | See [`commit-conventions.md`](./commit-conventions.md) |
| ADR file | `NNNN-kebab-title.md` | `0002-separate-from-sibling-products.md` |

When adding or renaming any identifier, update [`terminology.md`](../explanation/terminology.md) in the same PR; if it is a product concept, also update [`glossary.md`](../explanation/glossary.md).

---

## 11. Prohibited patterns

- **Typos or spelling variants of any term registered in `terminology.md`** (e.g. `tenant_id` for `organization_id`, `deposit_cents` for `amount_cents`) — blocks merge
- **Unregistered identifiers** — using an entity, status, field, slug, or `operationId` not present in `terminology.md` without adding it in the same PR
- Layer-first folders (`src/Handlers/`, `src/Repositories/`)
- SQL outside `Pdo*Repository`
- camelCase in public JSON property names
- Float or DECIMAL for money in SQLite tests or API JSON
- Renaming shipped `operationId` values
- **Implementing quote/invoice/line-item/PDF/tax logic in Clear** — that domain belongs to `nene-invoice` (ADR 0009); Clear reads invoices via the upstream API only

---

## Verification

```bash
composer check
composer openapi
composer mcp
```

Review checklists: [`docs/review/`](../review/).
