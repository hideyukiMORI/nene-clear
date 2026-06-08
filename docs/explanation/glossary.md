# Glossary

Canonical English terms for NeNe Clear public docs, OpenAPI, and code comments.

> This file defines the **meaning** of product concepts. The authoritative
> **spelling** of every term and identifier (entities, fields, status values,
> slugs) lives in the single source of truth
> [`terminology.md`](./terminology.md) — spellings here MUST conform to it.
>
> **Domain (ADR 0009):** NeNe Clear covers **payment reconciliation and
> dunning**. Quote, invoice, and qualified-invoice concepts belong to
> [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice); they appear
> here only as **upstream read models**.

| Term | Definition | Avoid |
| --- | --- | --- |
| **organization** | The tenant — an independent operator with its own users, Clear settings, and reconciliation data (ADR 0006) | "tenant" / "account" / "company" in code identifiers |
| **superadmin** | Cross-tenant platform role; manages organizations (`manage_organizations`) | "root", "owner", "superuser" in code |
| **admin** | Organization-scoped role; manages users, Clear settings, reconciliation, and dunning | "manager" |
| **member** | Organization-scoped operator; confirms/reverses matches and sends dunning when granted; no user/settings management | "user", "staff" in code identifiers |
| **viewer** | Organization-scoped read-only role; sees matched/unmatched lists and dunning history | "guest" |
| **organization resolution** | Per-request selection of the tenant; modes `single` (default) / `path` / `subdomain` / `custom_domain` | "tenant detection" |
| **reconciliation** (消込) | A confirmed link between one bank transaction (or a portion) and one or more upstream invoice payments | "matching" as a stored noun; "clearing" in code identifiers |
| **bank transaction** | One imported credit (deposit) line on the operator's bank account | "deposit row", "ledger line" |
| **bank import batch** | One CSV import with provenance (file hash, source filename, actor, timestamp) | "upload", "job" |
| **allocation** | One row of a reconciliation assigning an amount from a bank transaction to a single invoice (一括入金の按分) | "split", "distribution" in code |
| **partial payment** | A bank credit that covers only part of an invoice's outstanding balance; invoice becomes `partially_paid` upstream | "underpayment" |
| **overpayment** | A bank credit exceeding the invoice outstanding; the excess is recorded as **client credit**, never discarded | "surplus", "extra" |
| **client credit** | An overpayment balance held for a client and applied to a future invoice only by explicit operator action | "prepayment", "deposit" |
| **receivable** | The thing a bank deposit is reconciled against and dunning targets. Two **sources** (`source`): `invoice_upstream` — read from NeNe Invoice, which stays the system of record (ADR 0010); `manual` — entered directly in Clear (ADR 0014), which Clear owns because no other system holds it | "invoice" used generically for both |
| **manual receivable** | A receivable entered directly in Clear (single entry or CSV) when there is no NeNe Invoice upstream. A **reconciliation reference** carrying a `reference_number`, payer, amount, and due date — **not** an issued invoice / 適格請求書 / tax original (scope-contract X1). Clear computes its outstanding and status | "invoice" (it issues none), "bill" |
| **transfer fee mismatch** (振込手数料) | When the client pays net of bank transfer fee, so the credit is less than the invoice total; resolved by partial payment, fee absorption, or separate expense — never a silent write-off | "shortfall write-off" |
| **reversal** | Undoing a confirmed reconciliation by creating a reversal record (never a hard delete); upstream invoice outstanding is restored | "unmatch delete", "rollback" |
| **dunning** (督促) | Operator-controlled professional payment reminders for overdue/unpaid invoices, with logged send history; **not** debt collection or legal enforcement | "collection", "chasing" |
| **dunning notice** | One immutable send record (invoice, recipient, outstanding at send, template version, actor, timestamp) | "reminder log row" without the audit fields |
| **minimum interval** | The shortest allowed gap between dunning notices for the same invoice (default 7 days) | "cooldown", "throttle" in docs |
| **upstream / Invoice upstream** | NeNe Invoice, the required HTTP source of truth for invoices, outstanding balances, and payments (ADR 0009) | "backend", "billing service" loosely |
| **invoice** (upstream read model) | A billing document (請求書) owned by NeNe Invoice; Clear reads its number, dates, outstanding, and status read-only | treating it as a Clear-owned entity; recomputing its tax |
| **client** (upstream read model) | Customer / buyer (取引先) owned by NeNe Invoice; Clear reads name/contact for match hints and dunning | "customer" in code identifiers; storing as SSOT |
| **payment** (upstream) | A receipt against an invoice (入金); Clear creates/updates it via the Invoice API after a confirmed match, and stores only the link | recording payments as a primary Clear workflow |
| **outstanding balance** | `invoice.total_cents − sum(allocated payments)` for an invoice, as reported by the Invoice upstream | recomputing locally from cached figures |
| **degraded mode** | Operation when the Invoice API is unavailable: bank import still works, but match confirmation (which writes upstream) is blocked with an operator warning | "offline mode" |
| **paid_at** | The date a deposit was credited (bank value date), written to the upstream payment on match — not the date the operator clicked confirm | "match date", "confirm date" |
| **cents** | Integer amount in the **smallest currency unit**. For JPY (the only Phase 1–3 currency) one "cent" is one 円, so `amount_cents = 1000` means ¥1,000. The suffix is a fixed internal convention, not a sub-yen unit | float or DECIMAL money; reading `_cents` as 1/100 yen |
| **overdue** | An upstream invoice past `due_at` with unpaid balance — a computed flag owned by Invoice, mirrored read-only for dunning eligibility | treating it as a Clear-owned stored status |
| **audit event** | An immutable record of an import, match, reversal, dunning send, or credit creation (who / when / what) | "log line" without actor/timestamp |
| **Tier A** | Shared hosting deployment (ZIP + web installer + MySQL), beside NeNe Invoice on the same server | "rental server" in code |
| **Tier B** | Docker / VPS deployment — Invoice + Clear as two services | "cloud tier" |
| **handler** | HTTP entry point class | "controller" |
| **use case** | Business logic class with `execute()` | "service" (UseCase sense) |
| **UI locale** | Admin UI / operator-facing language. Bound to **ja (primary) + en (secondary) only** — not multilingual (ADR 0005). Distinct from the English-only dev-docs language policy | adding locales beyond ja/en |

When adding terms, update this table in the same PR.
