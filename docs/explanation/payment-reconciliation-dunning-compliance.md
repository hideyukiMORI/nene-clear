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

See also: [`scope-contract.md`](./scope-contract.md) (GOAL / DO / DON'T),
[`accounting-compliance.md`](./accounting-compliance.md),
[`scope-boundary.md`](./scope-boundary.md),
[`../integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md),
[`philosophy.md`](./philosophy.md) (human confirms, AI proposes),
[`../review/compliance.md`](../review/compliance.md).

---

## 0. Scope — what NeNe Clear is and is not

The full charter is [`scope-contract.md`](./scope-contract.md) (GOAL / DO /
DON'T). This section states the accounting-relevant scope.

### In scope — a reconciliation subledger over Invoice's books

NeNe Clear is a **reconciliation subledger and evidence custodian**, not the
billing ledger. It owns:

- **Imported bank deposit lines** as immutable evidence (証憑 / 電子取引データ)
- **Reconciliation links** between a bank line (or a portion of it) and an
  invoice payment
- **Client credit** balances arising from overpayment (前受金 / 預り金 相当)
- **Operator-controlled dunning** notices with full send history
- The **audit trail** for all of the above

The **invoice figures, the payment record itself, and the outstanding balance
are owned by NeNe Invoice** (the system of record — [ADR 0010](../adr/0010-payment-system-of-record.md)).
Clear reads them and writes payments back via the
[Invoice upstream contract](../integrations/invoice-upstream-contract.md); it
never stores them as its own truth. This keeps **帳簿 (Invoice) ↔ 証憑 (Clear)**
in one-to-one correspondence — the way an auditor expects.

### Out of scope (by design)

| Capability | Where it belongs |
| --- | --- |
| Quote / invoice / qualified-invoice issuance, consumption-tax calculation | **NeNe Invoice** (ADR 0009) |
| Payment / outstanding as a source of truth | **NeNe Invoice** (ADR 0010) |
| General ledger / journal entries (仕訳) | Accounting software (freee, Money Forward, Yayoi, etc.) — ADR 0013 |
| Bad-debt write-off (貸倒損失) as a tax event | Operator + 税理士 (法人税基本通達 9-6-1〜9-6-3) — §7 |
| Prescription / time-bar determination (消滅時効) | Operator + 弁護士 — §8 |
| Revenue recognition policy; consumption tax filing (申告) | Operator + tax advisor |
| Late/statutory interest (遅延損害金) auto-calculated as a legal claim | Off by default; optional template text only — §4.5, ADR 0011 |
| Third-party debt collection / 取立代行 / collection-agency conduct | Licensed 弁護士 / 債権回収会社 only — §4.8 (弁護士法72条) |
| Credit scoring | Not this product |

NeNe Clear **exports** reconciliation and payment data (CSV) for import into
accounting software. It does **not** replace double-entry bookkeeping (ADR 0013).

---

## 1. Statutory and professional basis

NeNe Clear targets compliance with the following **as they apply to billing
records, bank evidence, and payment reminders**. This table states *what we
design for*; it is not legal advice.

| Area | Relevant rules (Japan) | NeNe Clear role |
| --- | --- | --- |
| Bookkeeping & record retention | Corporation Tax Act / Income Tax Act bookkeeping & document retention (法人税法・所得税法 帳簿書類の保存) | Retain bank evidence, match, and dunning history 7–10 years; no auto-purge (§3.2) |
| Electronic records | Act on Electronic Books and Records Preservation (電子帳簿保存法) — electronic transaction data (電子取引データ) | 真実性の確保 (immutability + 訂正削除履歴) and 可視性の確保 (search by date/amount/counterparty) — §3 |
| Consumption tax | Consumption Tax Act (消費税法) — taxable period, qualified invoice rules | Record payment date accurately; **never compute tax**; invoice figures owned by Invoice (§5) |
| Appropriation of payments | Civil Code arts. 488–491 (民法 弁済の充当) | Operator directs allocation across debts; no silent auto-appropriation (§2.9) |
| Prescription of claims | Civil Code art. 166 (民法 消滅時効 — receivables generally 5 years) | Surface aging as information only; **no** legal time-bar determination (§10) |
| Late payment / interest | Civil Code art. 404 statutory rate (民法 法定利率, currently 3%, reviewed every 3 years); Interest Rate Act (利息制限法) | Reminders state facts; statutory interest off by default, not auto-added to balance (§4.5) |
| Non-lawyer legal services | Attorney Act art. 72 (弁護士法72条); Servicer Act (債権管理回収業に関する特別措置法) | Support **self-collection** of the operator's own receivables only; no third-party 取立代行 (§4.8) |
| Personal data | Act on the Protection of Personal Information (個人情報保護法) | Dunning uses client contact data only as operator instructs; log sends; tenant isolation (§4.6) |
| Receipts & stamp duty | Stamp Tax Act (印紙税法) — receipts (領収書) ≥ ¥50,000 | Clear does **not** issue 領収書; out of scope (scope-contract X15) |

This table states *what we design for*; it is **not** legal advice, and statutory
values (e.g. the 3% statutory rate, retention periods) **MUST be confirmed with a
licensed professional at implementation time**. When any rule changes, open a
**P0 Issue** and update this document before shipping affected features.

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

### 2.9 Appropriation of payments (弁済の充当) — MUST

When one deposit could satisfy **several** invoices but does not cover them all,
*which* invoices it clears is a legal question of appropriation (民法 488–491),
not a system default.

- The **operator chooses** how a deposit is appropriated across invoices
  (指定充当). Clear MAY suggest an order (e.g. oldest-first) but MUST present it
  as a suggestion to confirm, never apply it silently.
- Clear MUST NOT bake in a fixed statutory-appropriation order (法定充当) as if
  it were the only correct outcome; the parties' agreement and the operator's
  designation govern.
- The chosen appropriation is recorded in the allocation rows and the audit
  trail, so an advisor can see exactly which invoice each portion cleared.

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

### 3.2 Integrity of the record (真実性の確保) — MUST

電子帳簿保存法 lets a business satisfy the integrity requirement for electronic
transaction data by one of several means; NeNe Clear's chosen means is the
**"訂正削除の履歴が残るシステム"** approach (a system that retains a history of
corrections and deletions):

- Imported bank data is **never edited or deleted in place** (§3.1). Any
  correction is a **reversal import batch** that leaves the original visible.
- Every correction/reversal is recorded as an immutable `audit_event` (who /
  when / what / reason), so the full history of changes is preserved.
- This is the design rationale recorded in
  [ADR 0012](../adr/0012-electronic-records-bank-data.md). Operators who instead
  rely on timestamps or an internal handling규程 (事務処理規程) do so outside the
  software; Clear's guarantee is the correction-history method.

### 3.3 Visibility and search (可視性の確保 / 検索要件) — MUST

- **見読可能性:** imported data and reconciliation results MUST be viewable on
  screen and printable in an "整然・明瞭" (orderly and clear) form.
- **検索要件:** the system MUST allow searching imported bank data by, at minimum:
  1. **transaction date (取引年月日)** — including **range** (範囲指定),
  2. **amount (取引金額)** — including range,
  3. **counterparty (取引先)**,
  and by **combinations of two or more** of these. (For operators eligible for
  the relaxed requirement — e.g. small-scale businesses able to provide data on
  download request — the combination/range search may be waived; Clear still
  provides it by default.)
- **システム概要書等の備付け:** Clear ships a system-overview / operations
  document describing how electronic transaction data is stored and searched.

### 3.4 Retention — MUST

- Imported bank lines and reconciliation links follow **§3 (Retention) of
  [`accounting-compliance.md`](./accounting-compliance.md)** — minimum **7 years**,
  up to **10 years** where applicable (e.g. loss carryforward); no auto-purge.

### 3.5 Timestamps and audit — MUST

- System clock used for `imported_at` MUST be documented in the operator guide.
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

### 4.8 Self-collection boundary (弁護士法72条 / サービサー法) — MUST

Dunning in NeNe Clear is a tool for the operator to **remind their own customers
about the operator's own receivables**. That is lawful self-collection. The
following are hard prohibitions ([ADR 0011](../adr/0011-dunning-self-collection-only.md)):

- Clear MUST NOT be used to **collect a third party's debts for a fee**, nor
  present a feature that does so. Collecting others' claims as a business is
  reserved for **licensed attorneys (弁護士)** or **licensed servicers (債権回収
  会社)** under 弁護士法72条 and the Servicer Act (債権管理回収業に関する特別措置法).
- Dunning messages MUST NOT impersonate, or imply the involvement of, a lawyer,
  a collection agency, or a court.
- The product distinguishes **督促 (reminder)** from **取立 (collection
  enforcement)**: Clear sends factual reminders; it does not pursue coercive
  collection, and it MUST NOT include threatening or intimidating content (§4.4).

If a future feature would cross this line (e.g. an agency offering, or acting on
behalf of other businesses' receivables), it requires a separate product, legal
review by a 弁護士, and an ADR — it is **not** an incremental change here.

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

## 7. Bad debt (貸倒) — out of scope, MUST NOT determine

Whether a receivable becomes a **bad-debt loss (貸倒損失)** is a **tax judgment**
governed by 法人税基本通達 9-6-1〜9-6-3 (legal extinguishment, factual
uncollectibility, and the formal/one-year criteria). It depends on facts the
software cannot evaluate.

- NeNe Clear MUST NOT auto-classify any balance as 貸倒, post a write-off, or
  reduce a receivable as a tax event ([ADR 0013](../adr/0013-no-journal-entries-no-bad-debt.md), scope-contract X4).
- An operator MAY **pause dunning** or mark an invoice "collection paused" for
  their own workflow. This is **operational only** — it has **no** accounting or
  tax effect, is not a 貸倒 determination, and is recorded in the audit trail.
- The actual 貸倒損失 decision and its journal entry are made by the operator and
  their 税理士 in accounting software, using Clear's CSV export as evidence.

---

## 8. Prescription / time-bar (消滅時効) — information only, MUST NOT determine

Receivables are generally subject to a **5-year** prescription period under 民法
166 (from when the creditor could exercise the right; the pre-2020 short-term
professional periods were abolished). Whether a specific claim is time-barred —
and whether prescription was interrupted/renewed (時効の更新・完成猶予) — is a
**legal question**.

- NeNe Clear MAY **surface aging** (days overdue, age buckets) as operator
  information.
- It MUST NOT assert that a claim is time-barred, MUST NOT auto-expire or hide a
  receivable on age, and MUST NOT compute a prescription date as a legal fact.
- Any legal determination is for the operator and a 弁護士.

---

## 9. Professional review gate

Sign-off is a **gate**, not a nicety. The following MUST occur before the named
milestone ships, and the sign-off MUST be recorded in the milestone Issue or an ADR.

| Reviewer | Scope of review | Required before |
| --- | --- | --- |
| **税理士 / 公認会計士** | Payment-date sourcing; partial / overpayment / transfer-fee flows; appropriation (§2.9); immutability, 電帳法 integrity & search (§3); retention; CSV export columns map to their journal-import workflow | Phase 1 (reconciliation API) |
| **税理士 / 公認会計士** | Confirmation that Clear posts **no** journal entries and makes **no** 貸倒 determination (§7, ADR 0013) | Phase 1 |
| **弁護士** (or advisor competent in collection law) | Default dunning template wording; the self-collection boundary (§4.8, 弁護士法72条); statutory-interest handling (§4.5); absence of coercive/false content (§4.4) | Phase 2 (dunning) |

Any change that **deviates** from a rule in this document also re-triggers the
relevant reviewer's sign-off via an ADR (§0.2 of [`accounting-compliance.md`](./accounting-compliance.md)).

---

## 10. How this rule applies to every change

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

Last updated: 2026-05-30
