# Product Vision

> **Product name:** **NeNe Clear** — see [`philosophy.md`](./philosophy.md),
> [ADR 0007](../adr/0007-product-identity-nene-clear.md), and
> [ADR 0009](../adr/0009-separate-from-nene-invoice.md).

> **Domain boundary:** NeNe Clear is **not** [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice).
> Invoice owns **見積・請求・入金管理**. Clear owns **入金消込・督促管理** only.

NeNe Clear is a self-hosted **payment reconciliation and dunning** platform on
[NENE2](https://github.com/hideyukiMORI/NENE2). This document records why the
product exists, what it optimizes for, and how it relates to NeNe Invoice and
the rest of the ecosystem.

## Origin

After an operator issues invoices and records expected payments in
**NeNe Invoice**, daily work shifts to:

- matching **bank CSV deposits** to the right invoices in Excel
- tracking **what is still unmatched**
- sending **overdue reminders** from memory or personal templates

That work is a **different problem** from creating qualified invoices. It is
error-prone, hard to audit, and invisible to AI assistants.

NeNe Clear exists **only** for that post-billing operations layer — not for
quotes, PDF issuance, or tax field validation (those stay in Invoice).

## North Star

Operators and AI agents can:

- import **bank deposit lines** from CSV (major Japanese banks, one format at a time)
- see **unmatched deposits** and **overdue invoices** (from Invoice upstream)
- **propose and confirm** matches between bank lines and invoice payments
- handle **partial payments, transfer fees, and overpayments** with audit trail
- send **dunning notices** with logged history and minimum interval guards
- operate via admin UI, REST API, or MCP — with **human confirmation** on matches

NeNe Clear is **not** a PHP framework. It is a **product** that consumes NENE2
and **NeNe Invoice HTTP APIs**.

## What we explicitly do not build

| Capability | Owner |
| --- | --- |
| Quotes, invoices, line items | **NeNe Invoice** (`nene-invoice`) |
| Qualified invoice PDF | **NeNe Invoice** |
| Consumption tax on documents | **NeNe Invoice** |
| Manual payment entry (primary) | **NeNe Invoice** |
| Client master as billing SSOT | **NeNe Invoice** |
| Bank reconciliation | **NeNe Clear** (this product) |
| Dunning | **NeNe Clear** (this product) |

**NeNe Clear is not upper compatible with NeNe Invoice.** It is not a migration
target, superset, or "Phase 2 of Invoice." Operators who need both run **two
sibling applications**.

## Target operators

**Primary — Japan SMB office manager** using NeNe Invoice **or** another billing
tool (freee / マネーフォワード / 弥生 / Misoca / spreadsheet). Receivables come
from the NeNe Invoice upstream API when connected, or are entered / CSV-imported
directly ([manual receivables, ADR 0014](../adr/0014-accept-manual-receivables.md)),
so Clear runs standalone. They receive bank CSV weekly, spend hours in Excel
matching deposits, and send overdue emails manually.

**Secondary — Tier B developers** running Invoice + Clear on Docker Compose on
one VPS — two apps, HTTP between them, no shared database.

## Primary persona

> A **regional food ingredient wholesaler** uses NeNe Invoice for 見積 and
> 適格請求書. Each week the office manager downloads bank CSV and spends two
> hours in Excel matching deposits to invoices. Overdue reminders are sent from
> a personal Outlook template with no send log. They want **one reconciliation
> screen**, **confirmed matches with audit trail**, and **dunning history** —
> without moving invoice data out of Invoice.

## Primary use case

Clear works **with or without NeNe Invoice**. With it, step 1 connects the
upstream; without it, the operator enters or CSV-imports receivables directly
([manual receivables, ADR 0014](../adr/0014-accept-manual-receivables.md)) and
skips straight to bank import.

1. Operator configures **NeNe Invoice API** connection in Clear.
2. Operator imports **bank CSV** into Clear.
3. Clear shows unmatched deposits alongside open invoices from Invoice upstream.
4. Operator (or AI via MCP) **proposes** a match → operator **confirms**.
5. Clear calls Invoice API to record payment / update status; Clear stores
   reconciliation link and audit entry.
6. For overdue invoices, operator sends **dunning email** from Clear; send is logged.

## Deployment (planned)

| Target | Path | Notes |
| --- | --- | --- |
| **VPS + Docker (Tier B, recommended)** | `docker compose up` | Run receivables / bank / PII data on infrastructure you control |
| **Managed / install-service** | Hosted, or set up for you | For operators without ops capacity (see adoption review) |
| **Shared hosting (Tier A, discouraged)** | `public_html/install.php` | Possible but **not recommended** for bank/PII — at your own risk |

> Shared hosting (Tier A) is **possible** via the web installer
> (`public_html/install.php`) but **not recommended** for this data — no root,
> throttled SMTP, limited DKIM/backup; **use at your own risk**. See
> [`adoption-review-2026-06.md`](./adoption-review-2026-06.md).

## Philosophy (summary)

1. **One domain, done well** — reconciliation and dunning only; resist invoice scope creep.
2. **Invoice upstream is truth** — never duplicate issued invoice figures in Clear DB.
3. **Human confirms, AI proposes** — especially for matching bank lines.
4. **Compliance as structure** — [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md) is binding.
5. **Self-hosted OSS** — MIT; operator-owned data.

Full philosophy: [`philosophy.md`](./philosophy.md).

## Comparison

| Aspect | Excel + bank CSV | NeNe Clear |
| --- | --- | --- |
| Match audit trail | None | Who matched what, when |
| Overpayment handling | Ad hoc | `client_credit` + rules |
| Dunning history | None | Logged per invoice |
| AI / MCP | None | Propose match; human confirms |
| Invoice issuance | Separate (Invoice product) | **Out of scope** |

## Non-goals

- **Not** quote, invoice, or qualified invoice PDF (→ NeNe Invoice)
- **Not** upper compatible with or replacement for `nene-invoice`
- **Not** full accounting / general ledger
- **Not** a debt collection agency
- **Not** embedded inside NeNe Invoice or Records
- **Not** shared database with Invoice

## Success criteria (MVP complete)

- Operator connects to NeNe Invoice API
- Imports bank CSV, confirms one match, sees audit log
- Sends one dunning notice with logged history
- `composer check` green; OpenAPI documents Clear operations only

## Related

- Requirements: [`requirements.md`](./requirements.md)
- Domain boundary: [ADR 0009](../adr/0009-separate-from-nene-invoice.md)
- Upstream integration: [`../integrations/sibling-products.md`](../integrations/sibling-products.md)
- Compliance: [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
- Roadmap: [`../roadmap.md`](../roadmap.md)
