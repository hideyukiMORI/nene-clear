# Requirements

Functional and compliance requirements for **NeNe Clear only** — payment
reconciliation and dunning.

> **Out of scope:** quotes, invoices, qualified invoice PDFs, and primary payment
> recording → [`nene-invoice`](https://github.com/hideyukiMORI/nene-invoice).
> See [ADR 0009](../adr/0009-separate-from-nene-invoice.md).

See also: [`product-vision.md`](./product-vision.md),
[`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md).

---

## 1. Tenancy and user roles

Multi-tenant from the foundation — [ADR 0006](../adr/0006-multi-tenancy-and-roles.md).

| Role | Capabilities (Clear-specific) | Phase |
| --- | --- | --- |
| **superadmin** | Cross-tenant; manage organizations | 1 |
| **admin** | Bank import, match confirm/reverse, dunning send, upstream config | 1 |
| **member** | Match confirm, dunning send (if granted) | 1 |
| **viewer** | Read unmatched/matched lists, dunning history | 2+ |

Authorization: `Role` + `Capability` middleware. JWT for mutating routes.

---

## 2. Core entities (MVP)

Tenant-scoped entities carry **`organization_id`**.

| Entity | Purpose |
| --- | --- |
| **organization** | Tenant |
| **user** | Operator account |
| **clear_settings** | Upstream Invoice API URL, credentials, bank accounts, dunning defaults |
| **bank_import_batch** | CSV import provenance (hash, actor, timestamp) |
| **bank_transaction** | Imported deposit line; unmatched until reconciled |
| **payment_reconciliation** | Link bank_transaction ↔ Invoice payment (via API) |
| **client_credit** | Overpayment balance |
| **dunning_notice** | Send log per invoice |
| **audit_event** | Match, reverse, dunning, import |

**Not stored as SSOT in Clear:** invoice line items, tax figures, quote data —
fetched from Invoice upstream or cached read-only with TTL.

All money: **integer cents**. JPY only Phase 1–3.

---

## 3. Upstream — NeNe Invoice API

Clear **MUST** integrate with NeNe Invoice via HTTP (ADR 0009):

| Operation | Direction | Use |
| --- | --- | --- |
| List open / overdue invoices | Invoice → Clear (read) | Matching UI, dunning eligibility |
| Get invoice detail + outstanding | Invoice → Clear (read) | Match confirmation |
| Create / update payment | Clear → Invoice (write) | After match confirmed |
| List clients (optional) | Invoice → Clear (read) | Fuzzy name match hints |

If Invoice API is unavailable, Clear **MAY** operate in degraded mode (import
only, no match write) — document in operator guide.

Future adapters for non-Invoice billing sources require ADR.

---

## 4. Reconciliation requirements

Binding detail: [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md).

### Phase 1 — API

- [ ] Invoice upstream client + connection test
- [ ] Bank CSV import → `bank_import_batch` + `bank_transaction`
- [ ] List unmatched transactions and open invoices (from upstream)
- [ ] Manual match confirm → call Invoice payment API + store `payment_reconciliation`
- [ ] Match reversal with audit (no hard delete)
- [ ] Partial payment and overpayment flows per compliance doc
- [ ] OpenAPI + PHPUnit + PHPStan 8

### Phase 2 — Admin UI

- [ ] Reconciliation workspace (unmatched / suggest / confirm)
- [ ] Dunning list + send + history
- [ ] ja + en admin UI (ADR 0005)
- [ ] Dashboard: unmatched count, overdue count

### Phase 3 — Tier A

- [ ] Web installer (MySQL, admin user, Invoice API config)
- [ ] Release ZIP
- [ ] Operator guide

---

## 5. Dunning requirements

- [ ] Only for invoices in overdue/unpaid state from upstream
- [ ] Template with invoice number, dates, outstanding, bank instructions (from Invoice/upstream)
- [ ] Send log immutable (`dunning_notice`)
- [ ] Minimum interval between notices (default 7 days)
- [ ] No auto statutory interest on balance without ADR + advisor sign-off

---

## 6. API requirements

- JSON API, OpenAPI 3.1, RFC 9457 Problem Details
- snake_case JSON; pagination envelope
- Admin routes under `/admin/…`
- `GET /health` unauthenticated

---

## 7. Security

- Tenant isolation on all queries (ADR 0006)
- Upstream credentials in `.env` only
- Audit log for match and dunning (Phase 1+)
- No stack traces in production

---

## 8. Explicit non-goals

| Item | Owner / reason |
| --- | --- |
| Quotes, invoices, PDFs | **NeNe Invoice** |
| Upper compatibility with Invoice | ADR 0009 — separate products |
| Shared DB with Invoice | ADR 0009 |
| General ledger | Export CSV only |
| Bank API sync (MVP) | CSV first |
| Automatic match without confirm | Compliance + philosophy |

---

## 9. Acceptance tests (MVP)

1. Configure Invoice upstream URL + token.
2. Import bank CSV with 3 deposit lines.
3. Confirm match for one invoice → payment visible in Invoice; reconciliation row in Clear.
4. Reverse match → audit entry; invoice outstanding restored in Invoice.
5. Send dunning for one overdue invoice → `dunning_notice` row.

---

## Related

- Compliance: [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
- Upstream: [`../integrations/sibling-products.md`](../integrations/sibling-products.md)
- ADR 0009: Domain boundary
- Roadmap: [`../roadmap.md`](../roadmap.md)
