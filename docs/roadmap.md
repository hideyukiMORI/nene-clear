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

## Phase 3: Distribution (managed / install-service) — 🔲 Next

Reframed from "Tier A shared hosting": the 2026-06 adoption review
([`explanation/adoption-review-2026-06.md`](./explanation/adoption-review-2026-06.md))
found shared hosting a poor fit for receivables / bank / PII data (no root,
throttled SMTP, limited DKIM and backups). The recommended target is **VPS +
Docker (Tier B)** with a managed / install-service option for operators without
ops capacity. Tier A is **possible but not recommended** — a web installer
(`public_html/install.php`) ships as a PoC (#232) with an explicit
at-your-own-risk warning; it is not the recommended path for this data.

- Official Docker image + managed / install-service offering
- Operator deployment guide (VPS + Docker; Invoice + Clear as two apps)
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
