# ADR 0012: Electronic-Records Posture for Imported Bank Data (電子帳簿保存法)

## Status

accepted

> Engineering's interpretation of 電子帳簿保存法 — **not legal advice**. Confirm
> the applicable requirements (and any small-business relaxations) with a 税理士
> at implementation.

## Context

The bank deposit data NeNe Clear imports (CSV today) is **electronic transaction
data (電子取引データ)** used as accounting evidence. Under the Act on Electronic
Books and Records Preservation (電子帳簿保存法), such data must be preserved
electronically and must satisfy:

- **真実性の確保 (integrity):** by one of — timestamping, a system that retains a
  **history of corrections/deletions (訂正削除の履歴)**, or an internal handling
  規程 (事務処理規程).
- **可視性の確保 (visibility):** monitor/print legibility (見読可能性), a
  **search capability (検索要件)** over transaction date / amount / counterparty
  (with range and 2-or-more combinations), and availability of a system-overview
  document. (Some small-scale operators qualify for relaxed search requirements
  if they provide data on download request.)

We must choose how Clear meets these, so it is a design constraint from day one
rather than a retrofit.

## Decision

NeNe Clear meets 電子帳簿保存法 for imported bank data by the **correction-history
method**, plus full search:

1. **Immutability + reversal-only correction.** Imported `bank_transaction`
   amount, value date, and counterparty text are never edited or deleted in
   place. Errors are corrected via a **reversal import batch**; the original
   remains visible. Every correction is an immutable `audit_event` — this is the
   "訂正削除の履歴が残るシステム" approach to 真実性の確保.
2. **Provenance.** Each batch stores `file_hash` (SHA-256), `source_filename`,
   `row_count`, `imported_at`, `imported_by`; duplicate hash / line key warns or
   blocks re-import.
3. **Search (検索要件).** Imported data is searchable by **transaction date
   (取引年月日, incl. range), amount (取引金額, incl. range), and counterparty
   (取引先)**, and by **combinations** of two or more. Provided by default even
   where a relaxation might apply.
4. **Legibility + documentation.** Data is viewable/printable in an orderly,
   clear form, and Clear ships a system-overview / operations document
   describing storage and search.
5. **Retention.** 7 years minimum, up to 10 where applicable; no auto-purge.

## Consequences

**Benefits**

- A reviewing 税理士 can confirm 電帳法 compliance against concrete, named
  requirements rather than vague assurances.
- Immutability + audit history doubles as the reconciliation audit trail.

**Costs**

- No in-place edit of imported data, ever — corrections are always a reversal
  batch (more steps, but compliant).
- Search indexing on date/amount/counterparty is a hard requirement, not optional.

**Follow-up**

- Operator guide documents the system clock used for `imported_at` and the
  search workflow (compliance §3.3, §3.5).
- When non-CSV ingestion (全銀フォーマット, bank API, ZEDI) is added, re-confirm
  the same posture applies (separate ADR).

## Related

- Compliance: [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) §3
- Retention/audit principles: [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md) §3, §4
- Scope contract: [`../explanation/scope-contract.md`](../explanation/scope-contract.md) (D1, D2, X13)
- Supersedes: none
- Superseded by: none
