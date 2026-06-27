# ADR 0009: Separate Domain from nene-invoice

## Status

accepted

## Context

The NeNe portfolio has two **distinct back-office products** that operators
often conflate because both touch "money after billing":

| Product | Repository | Domain |
| --- | --- | --- |
| **NeNe Invoice** (working title) | `nene-invoice` (public) | **Quote, invoice, and payment management** — 見積・請求・入金管理 |
| **NeNe Clear** | `nene-clear` (public) | **Payment reconciliation and dunning** — 入金消込・督促管理 |

These are **not** the same product, **not** upper/lower layers of one stack,
and **not** a migration path (`invoice` → `clear`). They are **separate
deployable units** with separate databases, admin surfaces, and OpenAPI
contracts — integrated only via **documented HTTP**.

Early documentation incorrectly described NeNe Clear as "quote-to-cash billing"
and listed reconciliation as "Expansion #1" after invoice features. That
conflated two domains. This ADR corrects the boundary.

## Decision

### NeNe Clear owns ONLY

- Bank deposit import (`bank_transaction`, `bank_import_batch`)
- Matching bank lines to invoice payments (`payment_reconciliation`)
- Client credit from overpayment (`client_credit`)
- Overdue **dunning** notices (`dunning_notice`) — operator-controlled reminders
- Audit trail and CSV export for reconciliation/dunning events
- Compliance rules in `payment-reconciliation-dunning-compliance.md`

### NeNe Clear does NOT own

- Quotes (見積), invoices (請求), line items, qualified invoice PDF issuance
- Manual payment recording as the primary billing workflow
- Client master as billing SSOT (may cache read models from upstream)
- Consumption tax calculation on documents
- Any feature described as "Phase 1–3 core billing" in legacy Clear docs

Those belong to **`nene-invoice`**.

### Integration model

```
Operator
    ↓
NeNe Clear admin / MCP          NeNe Invoice admin / MCP
    ↓ HTTP (read + scoped write)     ↓
NeNe Clear API                  NeNe Invoice API
    ↓                               ↓
NeNe Clear DB                   NeNe Invoice DB
```

- **Dependency direction:** `NeNe Clear → NeNe Invoice API`. Never embed Clear
  in Invoice or share databases.
- Clear **reads** issued invoices, outstanding balances, and payment records from
  Invoice API for matching and dunning.
- Clear **writes** reconciliation outcomes back via Invoice API (create/update
  payment, update status) — never direct SQL to Invoice tables.
- Invoice **never** implements bank import or dunning — those stay in Clear.

### Not upper compatible

- Installing or upgrading **NeNe Clear does not replace NeNe Invoice**.
- **NeNe Clear is not a superset** of `nene-invoice` features.
- Operators may run **Invoice only**, **Clear only** (limited value), or
  **both** — the intended production path is **both**, side by side.

## Consequences

**Benefits**

- Clear product story: one noun, one pain (Excel bank CSV + overdue reminders).
- Invoice repo keeps public experiment momentum without Clear scope creep.
- Tax advisors review Invoice (documents) and Clear (reconciliation/dunning) separately.

**Costs**

- Two repos, two installs (or two apps on one server), cross-repo OpenAPI contract.
- Legacy Clear docs about quotes/invoices must be removed or redirected.

**Follow-up**

- Document Invoice upstream env vars in `docs/integrations/sibling-products.md`.
- Rewrite `requirements.md`, `product-vision.md`, `roadmap.md` for Clear-only MVP.
- Publication-strategy decision 0004 updated to match this split.

## Related

- ADR 0002: Sibling products (Records, Concierge, Corpus)
- ADR 0007: Product identity
- [`payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md)
- Publication-strategy: [`0004-nene-clear-product-strategy.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/decisions/0004-nene-clear-product-strategy.md)
- Issue: #5
