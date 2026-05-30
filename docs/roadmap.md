# Roadmap

NeNe Clear — **payment reconciliation and dunning** on NENE2. **Not** quote or
invoice issuance ([ADR 0009](./adr/0009-separate-from-nene-invoice.md)).

Billing documents: [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice).

## North Star

Operators self-host Clear beside NeNe Invoice to:

- import bank CSV deposits
- confirm matches to invoice payments (via Invoice API)
- send logged dunning notices for overdue receivables

## Phase 0: Governance and Foundation — ✅ Complete

- Governance docs, ADR 0001/0002/0009 ✅
- Product vision scoped to reconciliation/dunning ✅
- Scope contract + legal spine (ADR 0010–0013) ✅
- Invoice upstream contract (hand-off to nene-invoice) ✅
- NENE2 scaffold + `GET /health` ✅
- Professional review gate (税理士/公認会計士; 弁護士 for dunning) ✅

## Phase 1: Reconciliation API — ✅ Complete

- Multi-tenant + JWT + RBAC (ADR 0006) ✅
- `clear_settings`, `bank_import_batch`, `bank_transaction` ✅
- Invoice upstream: list open invoices, post payment on match confirm ✅
- `payment_reconciliation`, match reversal, audit events (with before/after) ✅
- Client credit (overpayment) + reconciliation idempotency ✅
- Compliance per `payment-reconciliation-dunning-compliance.md` ✅
- OpenAPI + PHPUnit + PHPStan 8 ✅

## Phase 2: Admin UI + Dunning — ✅ Complete

- Reconciliation workspace ✅
- Dunning send + history (single template; per-org customization → Phase 3+) ✅
- ja + en UI (ADR 0005) ✅
- Dashboard (unmatched count, recent dunning) ✅
- React + TypeScript SPA (`frontend/`); Vitest + Playwright E2E; CI ✅

## Phase 3: Tier A Shared Hosting — 🔲 Next

- Web installer + release ZIP
- Operator guide for the Invoice + Clear two-app setup
- Same-origin or subdomain admin
- Operator-editable dunning templates per organization

## Phase 4: Ecosystem — 🔲

- MCP tools (`listUnmatchedTransactions`, `proposeMatch`, `sendDunningNotice`)
- Optional Records/Concierge links (unchanged — HTTP only)
- CSV export for accounting software

## Not on this roadmap

The following belong to **`nene-invoice`**, not Clear:

- Quotes, invoices, qualified invoice PDF
- Purchase orders, contracts, subscriptions, expenses
- Any "Expansion #2–5" billing document features from legacy Clear docs

See [`docs/explanation/scope-boundary.md`](./explanation/scope-boundary.md).

## Non-goals

See [`docs/explanation/product-vision.md#non-goals`](./explanation/product-vision.md#non-goals).
