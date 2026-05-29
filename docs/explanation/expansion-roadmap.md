# Post-MVP Expansion Roadmap

Maintainer-approved feature sequence **after** Phase 1–3 core billing (quote → invoice → manual payment recording → PDF/admin UI) is stable.

This document is the source of truth for **what comes next** and **in what order**. Individual expansions get their own Issues, ADRs (when needed), and milestone entries — do not jump ahead without updating this file.

See also: [`roadmap.md`](../roadmap.md) (Phase 0–4), [`requirements.md`](./requirements.md), [`product-vision.md`](./product-vision.md).

---

## Prerequisite

**Do not start Expansion #1 until:**

- Phase 1: `invoices`, `payments`, invoice status (`issued` / `partially_paid` / `paid`), overdue computation ✅
- Phase 2 (minimum): admin can list unpaid/overdue invoices and record a payment manually

Expansion features assume the **quote-to-cash core** exists. Building reconciliation on top of a half-finished invoice API creates rework.

---

## Approved sequence

| # | Theme (JA) | Theme (EN) | One line |
| --- | --- | --- | --- |
| **1** | 入金消込・督促管理 | Payment reconciliation & dunning | Match bank deposits to invoices; remind clients on overdue |
| **2** | 発注書・納品書管理 | Purchase order & delivery note | Outbound PO to suppliers; delivery notes linked to quotes/invoices |
| **3** | 契約期限・更新管理 | Contract term & renewal | Track contract end dates; renewal quotes and alerts |
| **4** | 小規模サブスク請求管理 | Small-scale subscription billing | Recurring invoices for fixed monthly/yearly amounts |
| **5** | 経費申請の最小版 | Minimal expense reimbursement | Employee expense requests with approval — not full accounting |

Implement **in numeric order** unless an Issue explicitly reprioritizes with ADR + roadmap update.

---

## Expansion #1 — Payment reconciliation & dunning（入金消込・督促管理）

### Why first

- Directly completes **quote-to-cash** after invoicing — highest daily-use value for office managers
- Extends existing `payment` and `invoice.overdue` domain — no new document type required
- Differentiates from “PDF invoice generator” tools; matches pain of Excel + bank CSV today
- Shared hosting friendly: CSV import first; no bank API required for MVP

### In scope (MVP)

| Area | Capability |
| --- | --- |
| **Bank import** | Upload bank CSV (major Japanese bank formats documented one at a time); parse into `bank_transaction` rows |
| **Reconciliation** | Match transaction → invoice by rules: exact amount, client name fuzzy match, transfer reference; manual match UI/API |
| **Partial / overpay** | Support partial match; remainder stays unmatched or credit balance (document in ADR) |
| **Dunning** | Overdue invoice list; dunning email templates (ja); manual send + optional scheduled reminder (cron/CLI) |
| **Audit** | Log who matched what and when; log dunning sends |

### Out of scope (Expansion #1)

- Automatic bank API / Moneytree / freee bank sync
- Payment gateway capture (Stripe, PayPay) — Phase 4 optional
- General ledger / journal entries
- Multi-invoice single transfer split logic beyond simple “allocate amount across invoices” (Phase 1.5 if needed)

### Planned entities (register in `terminology.md` before code)

| Concept | Working name | Notes |
| --- | --- | --- |
| Imported bank line | `bank_transaction` | Raw import row; unmatched until reconciled |
| Match link | `payment_reconciliation` | Links `bank_transaction` → `payment` or creates `payment` |
| Dunning run | `dunning_notice` | invoice_id, sent_at, template_id, channel (email) |

### Suggested delivery phases

1. **E1-a** — `bank_transaction` import + list unmatched
2. **E1-b** — Manual match → create/update `payment`, update invoice status
3. **E1-c** — Rule-based match suggestions (amount + date window)
4. **E1-d** — Dunning templates + send + history

---

## Expansion #2 — Purchase order & delivery note（発注書・納品書管理）

### Why second

- Extends document chain **before** billing: PO → delivery → invoice
- Natural for B2B SMB (wholesale, manufacturing) already in product persona
- Reuses line-item and PDF patterns from quotes/invoices

### In scope (MVP)

- **Purchase order** (`purchase_order`) to suppliers — separate from client-facing quote
- **Delivery note** (`delivery_note`) — links to quote or invoice; PDF; optional client signature flag (manual)
- Status: draft → sent → accepted / cancelled

### Out of scope

- Inventory / stock levels (NeNe Shop territory)
- EDI / B2B platform integration

---

## Expansion #3 — Contract term & renewal（契約期限・更新管理）

### Why third

- Builds on **client** master + **quote** renewal workflow
- Recurring revenue ops without full subscription engine (that's #4)

### In scope (MVP)

- **Contract** entity: client_id, title, start_at, end_at, auto_renew flag, renewal_notice_days
- Dashboard: contracts expiring in 30/60/90 days
- One-click “renewal quote” from expiring contract

### Out of scope

- Legal contract CLM (DocuSign, CloudSign)
- Automatic contract PDF storage (optional link URL only)

---

## Expansion #4 — Small-scale subscription billing（小規模サブスク請求管理）

### Why fourth

- Depends on stable invoice + payment + preferably reconciliation (#1)
- “10 monthly retainers” not “Stripe Billing scale”

### In scope (MVP)

- **Subscription** plan: client, amount_cents, interval (monthly/yearly), next_invoice_at
- Cron/CLI generates draft invoice on schedule; operator approves or auto-issue (config)
- Pause / cancel subscription

### Out of scope

- Proration, metered usage, multi-plan upgrades
- Card-on-file / direct debit (link to external PSP in Phase 4+)

---

## Expansion #5 — Minimal expense reimbursement（経費申請の最小版）

### Why last

- Different actor (employee vs client); approval workflow adds RBAC surface
- Explicitly **not** full accounting — stays minimal to avoid freee/MF overlap

### In scope (MVP)

- **Expense report**: submitter, date, amount_cents, category, receipt image upload, status (draft/submitted/approved/rejected)
- Approver = `admin`; single-step approval
- Export CSV for accounting software — no journal posting

### Out of scope

- Commute / per-diem rules, corporate card feed, payroll integration

---

## Relationship to Phase 4 (ecosystem)

| Phase 4 item | Expansion touchpoint |
| --- | --- |
| NeNe Concierge lead → quote | Independent; can ship before Expansion #1 |
| NeNe Records catalog import | Helps Expansion #2 line items |
| MCP tools | Add per expansion (e.g. `listUnmatchedTransactions`, `sendDunningNotice`) |
| CSV export | Expansion #5 depends on it |

---

## How to start an expansion

1. Confirm prerequisite phase complete in `docs/todo/current.md`.
2. Open a GitHub Issue: `feat: Expansion #N — <short name>`.
3. Register new terms in `terminology.md` + `glossary.md` in the same PR.
4. Add ADR if architecture choice is non-obvious (e.g. bank CSV format strategy, dunning scheduler on Tier A).
5. Update this file only if **order or scope** changes — with maintainer approval.

---

## Current focus

**Next implementation target after Phase 1–2 core:** **Expansion #1 — Payment reconciliation & dunning.**

Last updated: 2026-05-29 (Issue #27)
