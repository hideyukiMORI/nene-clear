# Glossary

Canonical English terms for NeNe Clear public docs, OpenAPI, and code comments.

> This file defines the **meaning** of product concepts. The authoritative
> **spelling** of every term and identifier (entities, fields, status values,
> slugs) lives in the single source of truth
> [`terminology.md`](./terminology.md) — spellings here MUST conform to it.

| Term | Definition | Avoid |
| --- | --- | --- |
| **organization** | The tenant — an independent issuer with its own users, issuer profile, and billing data (ADR 0006) | "tenant" / "account" / "company" in code identifiers |
| **superadmin** | Cross-tenant platform role; manages organizations (`manage_organizations`) | "root", "owner", "superuser" in code |
| **admin** | Organization-scoped role; manages the org's users, issuer settings, and billing | "manager" |
| **member** | Organization-scoped billing operator; creates/sends documents and records payments; no user/settings management | "user", "staff" in code identifiers |
| **organization resolution** | Per-request selection of the tenant; modes `single` (default) / `path` / `subdomain` / `custom_domain` | "tenant detection" |
| **quote** | An estimate (見積書) sent to a client before work is confirmed; may convert to an invoice | "estimate" in code identifiers |
| **invoice** | A billing document (請求書) issued to a client | "bill" |
| **qualified invoice** | Japan invoice system compliant document (適格請求書) with required issuer, tax, and registration fields | mixing Japanese in API field names |
| **issuer** | The operator's company that issues quotes and invoices (自社) | "seller", "supplier" in code |
| **client** | Customer / buyer (取引先) in the billing system | "customer" in code identifiers |
| **line item** | A single row on a quote or invoice (品名, quantity, unit price, tax rate) | "row", "detail" |
| **payment** | A recorded receipt against an invoice (入金) | "receipt" (PDF noun) |
| **registration number** | Japan invoice registration number (インボイス登録番号), format `T` + 13 digits. API validation is **syntax only** — format, not existence or check digit | "T-number" without context; treating regex pass as proof of validity |
| **cents** | Integer amount in the **smallest currency unit**. For JPY (the only Phase 1–3 currency) one "cent" is one 円, so `total_cents = 1000` means ¥1,000. The suffix is a fixed internal convention, not a sub-yen unit | float or DECIMAL money; reading `_cents` as 1/100 yen |
| **tax rate bps** | Tax rate in basis points (1000 = 10.00%, 800 = 8.00%) | float percentages in DB |
| **quote-to-cash** | Flow from estimate through invoice to payment | "order-to-cash" (ERP term) |
| **Tier A** | Shared hosting deployment (ZIP + web installer + MySQL) | "rental server" in code |
| **Tier B** | Docker / VPS deployment | "cloud tier" |
| **handler** | HTTP entry point class | "controller" |
| **use case** | Business logic class with `execute()` | "service" (UseCase sense) |
| **sync PDF download** | Single HTTP response returning PDF bytes | "streaming PDF" |
| **overdue** | Invoice past `due_at` with unpaid balance — computed status | stored status in Phase 1 (optional) |
| **UI locale** | Admin UI / operator-facing language. Bound to **ja (primary) + en (secondary) only** — not multilingual (ADR 0005). Distinct from the English-only dev-docs language policy | adding locales beyond ja/en; conflating UI locale with statutory invoice language |

When adding terms, update this table in the same PR.
