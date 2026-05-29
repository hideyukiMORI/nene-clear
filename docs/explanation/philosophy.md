# Philosophy — NeNe Clear

**NeNe Clear** — *Clear deposits. Collect with confidence.*

This document records **ideals**, **philosophy**, and **non‑negotiable beliefs**
for the product. Read [ADR 0009](../adr/0009-separate-from-nene-invoice.md) first:
**Clear is not NeNe Invoice.** Different domain, different repo, not upper compatible.

| Product | Domain |
| --- | --- |
| **NeNe Invoice** (`nene-invoice`) | Quote, invoice, payment management |
| **NeNe Clear** (this repo) | Payment reconciliation & dunning |

---

## 1. What we believe

### 1.1 Reconciliation is its own job

Issuing a correct 適格請求書 and **knowing which deposit cleared which invoice**
are different skills, different workflows, and different failure modes.

**Ideal:** Operators use **NeNe Invoice** for documents and **NeNe Clear** for
matching bank reality to receivables — without Excel in between.

### 1.2 Self-hosting applies here too

The office manager already self-hosts Invoice beside WordPress. Reconciliation
data (bank lines, match history, dunning sends) should stay on the same
infrastructure — not in another SaaS.

### 1.3 Compliance is structure, not email templates

Reconciliation and dunning touch **電子帳簿保存法**, audit expectations, and
professional reminder boundaries. Rules live in
[`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)
— not in someone's memory.

### 1.4 Human confirms, AI proposes

Bank CSV import can be automated; **match confirmation** must not be silent.
AI suggests; operator confirms; audit log records the decision.

### 1.5 Narrow scope is a feature

We refuse to absorb Invoice's domain "for convenience." One product that does
見積 through 督促 becomes freee. **Clear does 消込 and 督促 only.**

---

## 2. Philosophy (how we build)

### 2.1 Clear over clever

Explicit layers, integer cents, registered terms ([`terminology.md`](./terminology.md)),
OpenAPI before UI.

### 2.2 Invoice upstream, Clear downstream

```
NeNe Invoice API  →  (read invoices, outstanding, payments)
                   ←  (write payment / status via API after match)
NeNe Clear        →  (bank import, reconciliation, dunning — owned here)
```

Never share databases. Never fork Invoice code into Clear.

### 2.3 Same contract for GUI and MCP

Every operator action in admin UI is reachable via documented HTTP/MCP.

### 2.4 Sibling products, separate repos

Records, Corpus, Concierge, **Invoice**, and Clear are independent deployables
(ADR 0002, ADR 0009).

---

## 3. Ideals checklist

When evaluating a PR, ask:

1. Does it belong to **reconciliation or dunning** — not billing documents?
2. Does it treat **Invoice as upstream truth** for invoice figures?
3. Does it leave an **audit trail** for matches and dunning sends?
4. Does it require **human confirmation** before finalizing matches?
5. Would it make someone think Clear **replaces** Invoice? (If yes, reject.)

---

## 4. What we refuse to become

| We are not | Why |
| --- | --- |
| NeNe Invoice or its successor | ADR 0009 — separate domain |
| Quote / invoice / PDF tool | Invoice repo |
| General ledger | Export CSV; accounting software posts journals |
| Debt collection agency | Operator-controlled reminders only |
| Upper compatible superset of Invoice | Explicit non-goal |

---

## 5. Name — why *Clear*

| Reading | Meaning |
| --- | --- |
| **Clear (verb)** | Reconcile bank lines to invoices (消込) |
| **Clear (adjective)** | Transparent unmatched / overdue status |
| **Not** | "Clear everything from quote to cash" in one app — that's Invoice + Clear together |

---

## 6. Portfolio position

```
Back office
  ├── NeNe Invoice  — 見積 · 請求 · 入金管理
  └── NeNe Clear    — 入金消込 · 督促管理   ← this product
```

Front office (Records, Corpus, Concierge) is unchanged. Clear does not replace Invoice.

---

## Related

- ADR 0007: Product identity
- ADR 0009: Domain split from nene-invoice
- [`product-vision.md`](./product-vision.md)
- [`payment-reconciliation-dunning-compliance.md`](./payment-reconciliation-dunning-compliance.md)

Last updated: 2026-05-29
