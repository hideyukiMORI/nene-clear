# Product Vision

> **Product name:** **NeNe Clear** — see [`philosophy.md`](./philosophy.md) and
> [ADR 0007](../adr/0007-product-identity-nene-clear.md). Repository: `nene-clear` (private until launch).

NeNe Clear is a self-hosted quote and invoice platform built on [NENE2](https://github.com/hideyukiMORI/NENE2). This document records why the product exists, what it optimizes for, and how it relates to the NeNe ecosystem.

## Origin

Every Japan SMB must issue **qualified invoices** under the Qualified Invoice System. SaaS accounting tools (freee, Money Forward, Yayoi) solve this well but charge recurring fees and hold financial data on vendor infrastructure. Many micro-businesses on **shared hosting** already run their website on the same server — they want billing documents without another monthly subscription.

NeNe Clear offers an alternative: **run quote and invoice management on infrastructure you control**, with source code you can audit, and optional integration with sibling NeNe products for catalog and lead capture.

The product showcases NENE2's strengths — OpenAPI-first APIs, Clean Architecture, MCP-ready ops boundaries, and field-trial-grade security — in a **back-office application** every SMB needs, not a demo endpoint.

## North Star

Operators and AI agents can:

- register company issuer profile (name, address, invoice registration number, bank details)
- manage **clients** (buyers) with billing addresses
- create **quotes** with line items and tax breakdown
- convert accepted quotes to **invoices** with one action
- issue **qualified invoice** PDFs that meet Japan invoice system requirements
- record **payments** and see overdue / unpaid status at a glance
- optionally import product names from [NeNe Records](https://github.com/hideyukiMORI/nene-records) or create draft clients from [NeNe Concierge](https://github.com/hideyukiMORI/nene-concierge) leads
- operate all of the above via admin UI, REST API, or MCP tools

Operators keep financial document data on their stack. End clients receive PDF or email — they never need an account.

NeNe Clear is **not** a PHP framework. It is a **product** that consumes NENE2.

## Target Operators and Markets

**Primary — Japan SMB on Tier A shared hosting**

Companies with 1–20 staff who already pay for shared hosting (Xserver, Sakura, Lolipop, etc.) and find SaaS accounting monthly fees heavy. A general-affairs or accounting staff member — often not an engineer — manages invoices today in Excel or paper. After Phase 3, they should install via **web installer**, enter company registration number, create clients and invoices from admin UI — without Docker or CLI.

**Secondary — Tier B developers and VPS operators**

Docker Compose for local development and production. Same API and admin UI as Tier A.

**Non-Japanese operators doing business in Japan**

A growing segment: founders and staff who are not native Japanese speakers but
run a company registered in Japan. They are bound by the same Japanese invoice
and tax rules, but operate the admin UI more comfortably in English. NeNe Clear
serves them with an **English admin UI**, while statutory documents remain
Japanese — see [ADR 0005](../adr/0005-bilingual-ja-en-scope.md).

**Multi-tenant hosting operators**

Agencies running one NeNe Clear instance for multiple client organizations.
Multi-tenancy is **foundational, not deferred** — every tenant-scoped table
carries `organization_id` and a per-request resolver selects the tenant
([ADR 0006](../adr/0006-multi-tenancy-and-roles.md)). A single-SMB install simply
runs in the default `single` resolution mode. A **superadmin** manages
organizations; an organization **admin** manages that organization's users and
issuer settings; **members** operate billing. Same pattern as NeNe Records /
Concierge multi-tenancy.

## Primary Persona

A fictional but representative operator:

> A **regional food ingredient wholesaler** with 8 employees runs its website on shared hosting. The **office manager** creates quotes in Excel, converts to PDF manually, and tracks payments in a spreadsheet. Since the invoice system started, they must include registration numbers and correct tax rates on every document. freee is ¥1,980/month — leadership prefers a **one-time setup on existing hosting** plus no recurring SaaS. After Phase 3, they install NeNe Clear beside their WordPress site, enter clients once, issue qualified invoice PDFs from admin UI, and email them — paying only for hosting they already have.

The same pattern applies to **small manufacturers**, **creative agencies**, **equipment dealers**, and any B2B SMB that quotes before invoicing.

## Primary Use Case

NeNe Clear optimizes for **quote-to-cash for B2B SMB**:

1. Operator registers **issuer profile** (legal name, address, invoice registration number).
2. Operator adds **clients** (buyers).
3. Operator creates a **quote** with line items (description, quantity, unit price, tax rate).
4. Client accepts → operator converts quote to **invoice**.
5. System generates **qualified invoice PDF** with required fields.
6. Operator sends PDF by email or download link.
7. Client pays → operator records **payment** → invoice marked paid.
8. (Expansion #1) Operator imports bank CSV → confirms match → outstanding cleared with audit trail.

**Not the primary story:** full double-entry bookkeeping, payroll, expense reimbursement, inventory, or POS. Those belong to other products or Phase 4+ integrations.

## Dual Deployment (planned — ADR 0003)

Same codebase, two installation paths:

| Tier | Path | Admin access |
| --- | --- | --- |
| **Tier A — shared hosting** | Release ZIP + web installer + MySQL | Browser admin SPA |
| **Tier B — Docker / VPS** | `docker compose up` | Browser admin SPA |

## Philosophy

### 1. Quote-to-cash, not full accounting

NeNe Clear owns the document flow from estimate to payment tracking. It does not replace a tax accountant or ERP. Export to CSV for accounting software is a Phase 2+ feature, not day-one scope.

### 2. Japan invoice compliance as first-class data

Qualified invoice fields are validated at the API layer — not optional PDF templates. Registration number format, tax rate (10% / 8%), reduced-rate flags, and issuer/buyer identification are domain rules in UseCases.

### 3. Integer cents everywhere

All amounts stored and transmitted as integer cents. No floats in database or JSON. PDF rendering uses the same cents values the API calculated.

### 4. Self-hosted OSS first

MIT license. Operators control data. SMTP for email delivery uses operator-provided credentials — no vendor mail relay required.

### 5. MCP for operators, not for clients

MCP tools map to admin API operations ("list overdue invoices", "create quote for client X"). Client-facing PDF download uses tokenized URLs — not MCP.

### 6. Japanese and English only — not multilingual

The product localizes to **Japanese (primary) and English (secondary) only**.
More non-Japanese operators now run businesses inside Japan; they work under
Japanese accounting rules but prefer an English admin UI, so English is
first-class. Arbitrary multilingual support is a deliberate **non-goal**: the
domain is locked to Japanese invoice/tax rules, so additional locales add
translation and maintenance surface without serving any real operator. The
qualified invoice PDF's statutory content stays Japanese (legal document); en
applies to the operator UI and guides. See [ADR 0005](../adr/0005-bilingual-ja-en-scope.md).

### 7. Separation from sibling products

NeNe Records owns CMS content. NeNe Corpus owns knowledge chat. NeNe Concierge owns scenario chat. NeNe Clear owns billing documents. Integration is HTTP-only — ADR 0002.

```
NENE2 (framework)
  ├── NeNe Records   (CMS · optional product catalog upstream)
  ├── NeNe Corpus    (knowledge chat — no default link)
  ├── NeNe Concierge (scenario chat · optional lead upstream)
  └── NeNe Clear   (quote · invoice · payment — this repo)
```

## Comparison

| Aspect | SaaS accounting (freee/MF) | Excel + manual PDF | NeNe Clear |
| --- | --- | --- | --- |
| License / cost | Monthly subscription | Free but error-prone | OSS + hosting you already pay |
| Data location | Vendor cloud | Local files | Your server |
| Qualified invoice | Built-in | Manual | Built-in, API-validated |
| AI / MCP ops | Vendor lock-in | None | OpenAPI + MCP catalog |
| Ecosystem link | None | None | Records / Concierge HTTP |

## Non-goals

See also [`requirements.md`](./requirements.md#explicit-non-goals).

- **Not** full double-entry accounting or general ledger
- **Not** payroll, expense reimbursement, or fixed-asset management
- **Not** inventory management or POS (NeNe Shop is a separate future product)
- **Not** a WordPress plugin (coexist on same domain is fine)
- **Not** embedded inside NeNe Records or other sibling repos
- **Not** structured e-invoice (PEPPOL) network transmission in Phase 1–3 — PDF + email first
- **Not** payment gateway integration in Phase 1 — manual payment recording first; Stripe/PayPay in Phase 4+
- **Not** multilingual beyond Japanese and English — UI locales bound to ja/en by [ADR 0005](../adr/0005-bilingual-ja-en-scope.md)

## Success Criteria (Phase 3 complete)

- Operator on Tier A shared hosting installs without SSH
- Creates qualified invoice PDF with registration number in under 10 minutes after install
- `composer check` and admin smoke tests green
- OpenAPI documents all admin operations; MCP catalog validates

## Related

- Requirements: [`requirements.md`](./requirements.md)
- Domain model: [`domain-model.md`](./domain-model.md)
- Glossary: [`glossary.md`](./glossary.md)
- Roadmap: [`../roadmap.md`](../roadmap.md)
- Sibling boundaries: [`../integrations/sibling-products.md`](../integrations/sibling-products.md)
