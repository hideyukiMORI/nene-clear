# CSV Export — Accounting Handoff

**Status: design (binding once a 税理士 signs off the columns — §9 gate).**

NeNe Clear is a reconciliation subledger; it posts **no journal entries** and
makes **no 貸倒 determination** ([ADR 0013](../adr/0013-no-journal-entries-no-bad-debt.md)).
It hands reconciliation and payment data to the operator's accounting software
and tax advisor (税理士) as **CSV** — they post the journals. This document
defines the export columns.

> Engineering's interpretation — **not legal advice**. The exact column set MUST
> be confirmed with a 税理士 / 公認会計士 against their journal-import workflow
> at the [§9 professional review gate](./payment-reconciliation-dunning-compliance.md).

## Principles

- **Evidence chain.** Every exported payment row links the bank line (証憑, owned
  by Clear) to the upstream invoice and payment (帳簿, owned by NeNe Invoice).
  An auditor can trace 帳簿 ↔ 証憑 one-to-one.
- **Read snapshot.** Export is a read-only snapshot; it never mutates records.
  Re-exporting the same range yields the same rows (plus any new activity).
- **No journals, no tax math.** Tax figures are **copied from the upstream
  invoice** (Clear never recomputes them). No debit/credit columns.
- **Tenant-scoped.** Export is always scoped to the caller's `organization_id`.

## Money & encoding

- Amounts are exported as **integer minimum currency units** in `*_cents`
  columns. For JPY (Phase 1–3) one unit = ¥1, so `amount_cents = 110000` is
  ¥110,000. Column headers say `_cents` to prevent a 1/100-yen misread.
- Encoding: **UTF-8 with BOM** by default (opens cleanly in Excel with Japanese
  text). A **Shift_JIS (CP932)** option is available for accounting tools that
  require it. Newlines `\r\n`; RFC 4180 quoting.
- Dates as `YYYY-MM-DD`; timestamps as ISO 8601.

## Export 1 — Reconciled payments (primary)

One row per **allocation** (a portion of a bank deposit applied to one invoice).
This is the row an accounting import consumes.

| Column | Source | Notes |
| --- | --- | --- |
| `reconciliation_id` | Clear | `payment_reconciliation_id` |
| `allocation_id` | Clear | `reconciliation_allocation_id` |
| `status` | Clear | `confirmed` / `reversed` |
| `invoice_id` | upstream | NeNe Invoice id |
| `invoice_number` | upstream | e.g. `INV-2026-001` |
| `client_id` | upstream | |
| `issued_at` | upstream | invoice issue date |
| `paid_at` | Clear → upstream | **bank value date** of the deposit (入金日) |
| `amount_cents` | Clear | amount allocated to this invoice |
| `invoice_total_cents` | upstream | copied, not recomputed |
| `tax_breakdown` | upstream | copied as provided (opaque to Clear) |
| `payment_id` | upstream | payment created in NeNe Invoice |
| `external_reference` | Clear | `clear:recon:{id}` round-trip key |
| `bank_transaction_id` | Clear | the deposit line (証憑) |
| `value_date` | Clear | bank value date of the line |
| `counterparty_text` | Clear | remitter / 摘要 |
| `bank_account` | Clear | `bank_name` / `account_number` (registered account) |
| `confirmed_at` / `confirmed_by` | Clear | actor + time |
| `reversed_at` / `reversal_reason` | Clear | when `status = reversed` |

Reversed allocations are **included** (not deleted) so the advisor sees the full
history; the `status`/`reversed_at` columns mark them.

## Export 2 — Client credits

One row per overpayment credit (前受金/預り金 相当) and its applications.

| Column | Source |
| --- | --- |
| `client_credit_id`, `client_id`, `status` | Clear |
| `amount_cents`, `remaining_cents` | Clear |
| `source_bank_transaction_id` | Clear |
| `created_at` / `created_by` | Clear |

## Export 3 — Bank import evidence (電帳法 companion)

A faithful dump of imported deposit lines for the electronic-records trail
(complements, does not replace, the in-app searchable store — compliance §3):
`bank_import_batch_id`, `file_hash`, `source_filename`, `bank_transaction_id`,
`value_date`, `amount_cents`, `counterparty_text`, `status`, `imported_at`.

## Out of scope

- Debit/credit journal columns, account codes, or a 仕訳 layout (ADR 0013).
- Any 貸倒 / write-off classification column (§7 — Clear does not determine it).
- Consumption-tax filing figures beyond copying the invoice's `tax_breakdown`.

## Gate

Before the export endpoint ships (Phase 1+), the columns above are reviewed with
a 税理士 / 公認会計士 to confirm they map to the operator's journal-import
workflow (compliance §9). Record sign-off in the milestone Issue.

## Related

- No journals / no bad debt: [ADR 0013](../adr/0013-no-journal-entries-no-bad-debt.md)
- Compliance §5 (consumption tax relationship), §9 (review gate): [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
- Contract (figures): [`../integrations/invoice-upstream-contract.md`](../integrations/invoice-upstream-contract.md)
- Terminology (field spellings): [`./terminology.md`](./terminology.md)
