# Roadmap

NeNe Clear is a self-hosted quote and invoice OSS on NENE2 — Japan SMB billing without SaaS lock-in.

## North Star

Operators can self-host a billing platform that:

- manages clients and company issuer profile
- creates quotes and converts them to invoices
- issues Japan qualified invoice compliant PDFs
- tracks payment status and overdue items
- runs on **Tier A** shared hosting or **Tier B** Docker/VPS
- optionally integrates with NeNe Records and NeNe Concierge via HTTP

Full scope: [`docs/explanation/product-vision.md`](./explanation/product-vision.md), [`docs/explanation/requirements.md`](./explanation/requirements.md).

## Phase 0: Governance and Foundation

Goal: engineering discipline and product design before runtime code.

- Governance docs, ADR 0001/0002, inheritance map ✅
- Product vision, requirements, domain model ✅ (Issue #3)
- Multi-tenancy + role hierarchy adopted as foundational ✅ ADR 0006 (Issue #17)
- NENE2 consumer scaffold (tenant resolution + JWT auth + RBAC + `GET /health`), OpenAPI, CI 🔲 Issues #4–#7
- ADR 0003 dual deployment 🔲 Issue #7

Tracked by `docs/milestones/2026-05-governance-and-foundation.md`.

**Status: product docs complete; runtime scaffold next.**

## Phase 1: Core Billing API

Goal: tenancy, auth, client master, quotes, invoices, payments — API and DB only.

- `organizations`, `users`, `company_settings`, `clients`, `quotes`, `invoices`, `line_items`, `payments`, `document_sequences` — all tenant-scoped by `organization_id` (ADR 0006)
- Organization resolution (default `single`) + JWT auth + `Role`/`Capability` RBAC
- Organization CRUD (superadmin); user CRUD (admin)
- Japan qualified invoice field validation in UseCases
- Quote → invoice conversion
- OpenAPI + PHPUnit + PHPStan 8

See [`docs/explanation/requirements.md#phase-1--api-only`](./explanation/requirements.md#phase-1--api-only).

## Phase 2: Admin UI + PDF

Goal: operators manage billing without CLI.

- React admin SPA — ja + en locale catalogs (ADR 0005; no other locales)
- Server-side qualified invoice PDF (Japanese layout)
- Email delivery via SMTP
- Public PDF download token
- Dashboard: unpaid / overdue

## Phase 3: Tier A Shared Hosting

Goal: Japan SMB install path.

- Web installer + release ZIP
- Operator guide (Japanese)
- Same-origin admin on shared hosting

## Phase 4: Ecosystem Integration

Goal: connect to sibling products.

- NeNe Records product catalog import
- NeNe Concierge lead → draft client / quote webhook
- MCP tool catalog
- CSV export; optional payment gateway

## Post-MVP expansions (maintainer sequence)

After Phase 1–3 core billing is stable, implement in this order:

1. **Payment reconciliation & dunning**
2. **Purchase order & delivery note**
3. **Contract term & renewal**
4. **Small-scale subscription billing**
5. **Minimal expense reimbursement**

Compliance for #1: [`docs/explanation/payment-reconciliation-dunning-compliance.md`](./explanation/payment-reconciliation-dunning-compliance.md).

Full scope, prerequisites, and MVP boundaries: [`docs/explanation/expansion-roadmap.md`](./explanation/expansion-roadmap.md).

**Current expansion focus:** #1 (after core invoice + payment lands).

## Non-goals

See [`docs/explanation/product-vision.md#non-goals`](./explanation/product-vision.md#non-goals).
