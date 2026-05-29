# Payment Reconciliation & Dunning — Binding Compliance Rules

**Status: binding (non-negotiable) for all payment-matching and dunning
features in NeNe Clear.** This is the **core product domain** — not a post-MVP
expansion. NeNe Invoice owns billing documents (ADR 0009).

This document is the source of truth for how NeNe Clear implements
**payment reconciliation** and **dunning** in a
way that accounting and tax professionals can review without finding silent
deviations from documented practice.

This is **not legal advice**. It is engineering's binding interpretation of
obligations that apply when a Japan-registered business uses NeNe Clear to
match bank deposits to issued invoices and to send payment reminders. Where
interpretation is unclear, **stop and consult a licensed tax accountant
(税理士) or certified public accountant (公認会計士)** — record the resolution in
an ADR and update this document.

See also: [`accounting-compliance.md`](./accounting-compliance.md),
[`scope-boundary.md`](./scope-boundary.md),
[`philosophy.md`](./philosophy.md) (human confirms, AI proposes),
[`../review/compliance.md`](../review/compliance.md).

---

## 0. Scope — what NeNe Clear is and is not

### In scope

NeNe Clear maintains a **billing subledger**:

- Issued invoices and their outstanding balances
- Payment records tied to invoices
- Imported bank deposit lines and reconciliation links between deposits and payments
- Operator-controlled dunning notices with full send history

This supports **accounts receivable (売掛金) clearance at the document level** —
the operator and their advisor can see which invoice was paid, when, and from
which bank line.

### Out of scope (by design)

| Capability | Where it belongs |
| --- | --- |
| General ledger / journal entries (仕訳) | Accounting software (freee, Money Forward, Yayoi, etc.) |
| Revenue recognition policy for corporation tax | Operator + tax advisor |
| Consumption tax filing (申告) | Operator + tax advisor |
| Statutory interest calculation as legal claim | Operator judgment; optional template text only |
| Debt collection agency services | Not this product |
| Credit scoring or third-party collections | Not this product |

NeNe Clear **exports** reconciliation and payment data (CSV) for import into
accounting software. It does **not** replace double-entry bookkeeping.

---

## 1. Statutory and professional basis

NeNe Clear targets compliance with the following **as they apply to billing
records, bank evidence, and payment reminders**. This table states *what we
design for*; it is not legal advice.

| Area | Relevant rules (Japan) | NeNe Clear role |
| --- | --- | --- |
| Bookkeeping & evidence | Corporation Tax Act bookkeeping duty; Civil Code performance of obligations | Preserve tamper-evident payment and match history |
| Consumption tax | Consumption Tax Act — taxable period and qualified invoice rules | Payment date recorded accurately; invoice figures unchanged after issue |
| Electronic records | Act on Electronic Books and Records Preservation (電子帳簿保存法) | Retain imported bank data and reconciliation audit trail with searchability |
| Personal data | Act on the Protection of Personal Information (個人情報保護法) | Dunning uses client contact data only as operator instructs; log sends |
| Late payment | Civil Code; Interest Rate Act (法定利率) | Reminders state facts; no automated legal threats or coercive language |
| Fair business practice | Act against Unjustifiable Premiums and Misleading Representations (B2B context: professional tone) | Templates reviewed; operator controls send |

When rules change, open a **P0 Issue** and update this document before shipping
affected features.

---

## 2. Payment reconciliation — accounting rules

### 2.1 Definitions

| Term | Meaning in NeNe Clear |
| --- | --- |
| **Bank transaction** | One imported credit line on the company's bank account |
| **Payment** | Business record that an invoice received funds |
| **Reconciliation** | Confirmed link between one bank transaction (or portion) and one or more payments |
| **Outstanding balance** | `invoice.total_cents − sum(allocated payment amounts)` for that invoice |

### 2.2 Payment date (`paid_at`) — MUST

- **`paid_at` MUST reflect the date the deposit was credited** on the bank
  statement (入金日 / 取引日 on the import file), not the date the operator
  clicked "match" unless they explicitly override with documented reason.
- If import date and bank value date differ, **bank value date wins** unless an
  ADR documents a per-bank-format exception.
- Clear records payments via **bank import + confirmed reconciliation**, so
  `paid_at` is bank-sourced by default. Manual payment entry without a bank line
  is Invoice's domain (ADR 0009); any operator override of `paid_at` MUST carry a
  documented reason.

### 2.3 Partial payment — MUST

- Multiple payments MAY apply to one invoice.
- After each allocation, invoice status MUST be:
  - `partially_paid` when `0 < outstanding < total`
  - `paid` when `outstanding = 0`
- Allocated amounts MUST NOT exceed invoice outstanding without triggering
  **overpayment handling** (§2.5).
- Sum of allocations across invoices for one bank transaction MUST equal the
  transaction amount (or documented remainder per §2.5).

### 2.4 Transfer fee mismatch — MUST document

When the client pays net of bank transfer fee (振込手数料):

- The bank credit amount may be **less than** invoice total.
- Matching MUST NOT silently write off the difference.
- Operator MUST choose one of:
  1. **Partial payment** — allocate bank amount only; remainder stays outstanding
  2. **Fee absorption** — allocate full invoice amount with explicit fee
     write-off reason (audit log); requires `admin` capability
  3. **Separate fee expense** — record in accounting software; NeNe Clear
     records payment at bank amount only

Default: **(1) partial payment**. Options (2) and (3) MUST leave an audit entry.

### 2.5 Overpayment — MUST

When bank credit **exceeds** invoice outstanding:

- System MUST NOT discard the excess.
- Excess MUST be recorded as **client credit balance** (`client_credit` or
  equivalent — register in `terminology.md` before code).
- Operator MAY apply credit to a future invoice via explicit allocation — never
  automatic without confirmation.

### 2.6 Multi-invoice single deposit — MUST

One bank transaction MAY allocate to multiple invoices (一括入金の按分):

- Allocation rows MUST sum to transaction amount.
- Each row MUST reference `invoice_id` and `amount_cents`.
- Confirmation is **one human action** per transaction batch, not silent auto-split.

### 2.7 Reversal and correction — MUST

- **Unmatch / reverse reconciliation:** creates a **reversal record**; does
  NOT hard-delete payment or bank transaction history.
- Invoice status MUST be recomputed from remaining valid payments.
- Reason code and operator identity MUST be stored.

### 2.8 Human confirmation — MUST

Automatic match suggestions (rules or AI) MUST NOT finalize reconciliation
without **explicit operator confirmation** unless a future ADR defines a
narrow, low-risk auto-match subset (e.g. exact amount + exact invoice number in
transfer reference) with professional sign-off.

See [`philosophy.md`](./philosophy.md) §2.2.

---

## 3. Bank import — electronic records rules

Under the Act on Electronic Books and Records Preservation, imported bank data
used as evidence MUST be handled as **electronic transaction data** (電子取引データ).

### 3.1 Import integrity — MUST

| Requirement | Rule |
| --- | --- |
| Immutability | After import, `bank_transaction` amount, date, and counterparty text MUST NOT be edited in place |
| Correction | Erroneous imports are voided via reversal import batch, not silent edit |
| Provenance | Store `import_batch_id`, source filename, file hash (SHA-256), `imported_at`, `imported_by` |
| Account scope | Each batch tied to one company bank account registered in `clear_settings` |
| Duplicate detection | Same file hash or duplicate line key MUST warn or block re-import |

### 3.2 Retention — MUST

- Imported bank lines and reconciliation links follow **§3 (Retention) of
  [`accounting-compliance.md`](./accounting-compliance.md)** — minimum **7 years**,
  up to **10 years** where applicable; no auto-purge.
- Searchability: list/filter by date, amount, match status, client, invoice number.

### 3.3 Timestamps and audit — MUST

- System clock used for `imported_at` MUST be documented in operator guide.
- All match/unmatch actions logged per §4 (Audit trail) of `accounting-compliance.md`.

---

## 4. Dunning (督促管理) — legal and operational rules

Dunning in NeNe Clear means **professional payment reminders** for overdue or
unpaid issued invoices. It is **not** debt collection, legal enforcement, or
credit reporting.

### 4.1 Eligibility — MUST

Dunning MAY be sent only when:

- Invoice status is `issued`, `partially_paid`, or `overdue`
- Invoice is not voided
- Outstanding balance > 0
- Client has a deliverable email address (or operator chooses another logged channel)

Dunning MUST NOT target `draft` invoices.

### 4.2 Operator control — MUST

| Rule | Detail |
| --- | --- |
| Send authority | `admin` or `member` with explicit capability (`send_dunning`) |
| Trigger | Manual send per invoice OR scheduled job with org-level opt-in |
| No silent spam | Scheduled dunning MUST respect minimum interval (default **7 days** since last notice for same invoice) |
| Template change | Template version stored on each send record |

### 4.3 Template content — MUST include

- Issuer legal name and contact
- Invoice number, issue date, due date
- **Outstanding amount** (current, not original total if partially paid)
- Payment instructions (bank name, branch, account type, account number from `clear_settings`)
- Professional closing; no abusive or threatening language

### 4.4 Template content — MUST NOT include (unless operator custom template + advisor review)

- False claims of legal action ("we will sue immediately")
- Misrepresentation of statutory penalties
- **Automatic statutory interest calculation presented as a legal demand** —
  optional informational text MAY reference contract terms; default templates
  MUST NOT compute compound interest without ADR + advisor sign-off
- Third-party collection agency impersonation

### 4.5 Statutory interest (法定利率) — advisory note

B2B monetary debts may carry statutory interest under the Interest Rate Act
(current statutory rate: confirm with advisor at time of implementation). NeNe
Clear:

- MAY offer an **optional** template placeholder for contractually agreed or
  statutory interest — **disabled by default**
- MUST NOT auto-add interest to `outstanding` balance in the billing subledger
  without ADR and professional sign-off
- Operator remains responsible for correctness of any interest claim

### 4.6 Personal information — MUST

- Client email and name in dunning are personal data; operator is **data controller**
- Send history is retained for audit (who, when, to whom, template version)
- Logs MUST NOT include unrelated client data in the same export row

### 4.7 Channel — Phase E1-d

- Primary channel: **email** via operator SMTP
- Each send creates immutable `dunning_notice` row
- Future channels (postal log-only) require ADR

---

## 5. Relationship to consumption tax accounting

NeNe Clear does **not** determine whether the operator uses **invoice basis**
(請求書ベース) or **payment basis** (支払ベース) for consumption tax.

The system MUST:

- Keep **issued invoice tax figures immutable** after issue
- Record **payment dates** accurately for advisor export
- Export CSV columns: invoice number, issue date, paid_at, amount_cents, tax breakdown (from invoice), match id

Tax advisor uses export to prepare returns; NeNe Clear does not file.

---

## 6. Audit trail — MUST

In addition to [`accounting-compliance.md`](./accounting-compliance.md) §4 (Audit trail):

| Event | Minimum audit fields |
| --- | --- |
| Bank import batch | batch id, file hash, row count, actor, timestamp |
| Match confirmed | bank_transaction id(s), payment id(s), invoice id(s), amounts, actor, timestamp |
| Match reversed | reversal id, prior match id, reason, actor, timestamp |
| Dunning sent | invoice id, outstanding at send, recipient, template version, actor, timestamp |
| Client credit created | client id, amount, source transaction, actor, timestamp |

Audit records follow the same no-silent-mutation rule as issued invoices.

---

## 7. Professional review checklist

Before Phase 1 (reconciliation API) ships, a licensed **税理士 or 公認会計士** SHOULD sign off on:

1. Payment date sourcing from bank import
2. Partial / overpayment / fee mismatch flows
3. Immutability and retention of bank + match records
4. Default dunning template wording (Japanese primary)
5. Confirmation that subledger export columns meet their journal import workflow

Record sign-off in the Phase 1 milestone Issue or ADR.

---

## 8. How this rule applies to every change

Any change touching bank import, matching, payment allocation, client credit,
dunning templates, or send scheduling **MUST**:

1. Be reviewed against this document and [`../review/compliance.md`](../review/compliance.md).
2. State compliance impact in the PR.
3. If it deviates from any rule here, carry an ADR with professional sign-off.

If unsure whether a change has compliance impact, **assume it does**.

---

## Related entities (register before code)

| Concept | Entity | Canonical table (terminology.md §1) |
| --- | --- | --- |
| Imported bank line | `BankTransaction` | `bank_transactions` |
| Import batch | `BankImportBatch` | `bank_import_batches` |
| Match link | `PaymentReconciliation` | `payment_reconciliations` |
| Allocation row | `ReconciliationAllocation` | `reconciliation_allocations` |
| Client overpayment credit | `ClientCredit` | `client_credits` |
| Dunning send | `DunningNotice` | `dunning_notices` |

[`terminology.md`](./terminology.md) is the **single source of truth** for these
spellings; register any new term there before implementation. Singular forms used
in prose above refer to a single row of the corresponding table.

Last updated: 2026-05-29
