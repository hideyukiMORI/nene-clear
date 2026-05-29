# Philosophy — NeNe Clear

**NeNe Clear** (*Clear billing from quote to cash.*)

This document records **ideals** (理想), **philosophy** (理念・哲学), and
**non‑negotiable beliefs** for the product. It complements
[`product-vision.md`](./product-vision.md) (scope and personas) and
[`expansion-roadmap.md`](./expansion-roadmap.md) (feature sequence).

Canonical language: **English**. Maintainer intent in Japanese appears in
§8 where it aids nuance; public API and OpenAPI remain English.

Product name: [ADR 0007](../adr/0007-product-identity-nene-clear.md).

---

## 1. What we believe

### 1.1 Every SMB deserves a clear money trail

Small businesses do not fail because they lack features — they fail because
**money movement is opaque**. Quotes live in email, invoices in Excel, payments
in bank CSV, reminders in someone's memory.

**Ideal:** One self-hosted place where *what was promised*, *what was billed*,
*what arrived*, and *what is still owed* are **visible and auditable** — for
the office manager **and** for any AI assistant the team already uses.

### 1.2 Self-hosting is a feature, not nostalgia

Japan SMB on shared hosting (FTP, MySQL, no Docker) is not "legacy." It is a
**deliberate choice**: data stays on infrastructure the operator already pays for,
beside WordPress or a static site, without another SaaS bill.

**Ideal:** Tier A install feels as approachable as uploading a CMS — not as
punishment for being small.

### 1.3 Compliance is structure, not paperwork

適格請求書 and tax rules are not PDF decoration. They are **validation rules**
in the API — the same rules whether a human clicks "Issue" or an agent calls
`createInvoice` via MCP.

**Ideal:** A tax reviewer finds **zero deviations** from documented compliance
([`accounting-compliance.md`](./accounting-compliance.md)); any change requires
ADR and professional sign-off.

### 1.4 AI everywhere — but responsibility stays in the system

By the time this product reaches operators, many teams will use Claude, Codex, or
similar tools daily. That does **not** replace the product. It changes **how**
operators interact with it.

**Ideal — dual surface:**

| Actor | Surface | Responsibility |
| --- | --- | --- |
| **Human operator** | Admin UI | Confirms matches, sends dunning, owns decisions |
| **AI assistant** | OpenAPI + MCP | Proposes matches, drafts quotes, lists overdue — **never silent writes without audit** |
| **End client** | PDF / email | Receives documents; no account required |

FTP install does not mean "no AI." It means **AI runs on the client side**
(browser, desktop, MCP host) while **billing truth lives in MySQL** on the
server.

### 1.5 Small scope, long horizon

We refuse to become freee. We **embrace** being the narrow, excellent layer:
**quote → invoice → collect → clear** — then carefully add PO, contracts,
subscriptions, and minimal expenses ([expansion roadmap](./expansion-roadmap.md)).

**Ideal:** A wholesaler with eight staff gets 80% of their billing pain solved
without learning double-entry accounting.

---

## 2. Philosophy (how we build)

### 2.1 Clear over clever

- Explicit layers (Handler → UseCase → Repository), not magic.
- Integer cents, not floats. Registered terms, not synonyms
  ([`terminology.md`](./terminology.md)).
- OpenAPI before UI assumptions.

If an agent or junior developer cannot find the rule in docs, the design failed.

### 2.2 Human confirms, AI proposes

Especially for **payment reconciliation** (Expansion #1):

- Import and structure bank data in the system.
- Rules and AI **suggest** matches.
- A human **confirms** → audit log → `payment` + invoice status update.

Automatic clearing without confirmation is a liability, not a feature, until
explicitly ADR'd for a defined low-risk subset.

### 2.3 Same contract for GUI and MCP

Everything an operator can do in admin UI must be reachable via documented HTTP
(and MCP where appropriate). No hidden SQL paths for agents.

This mirrors NeNe Concierge's "GUI parity with API" principle — applied to
**back-office billing**.

### 2.4 Sibling products, separate repos

NeNe Records, Corpus, and Concierge remain upstream/downstream **via HTTP only**
(ADR 0002). Clear owns billing data; it never merges into CMS or chat repos.

### 2.5 Bilingual operators, Japanese law

Admin UI: **Japanese + English** (ADR 0005). Statutory invoice content:
**Japanese**. The product serves non-Japanese founders running Japan-registered
entities — compliance is fixed; UI language is flexible.

---

## 3. Ideals checklist (north star behaviors)

When evaluating a feature or PR, ask:

1. Does it make the **quote-to-cash loop clearer** for a non-engineer?
2. Does it work on **Tier A** without Redis, without CLI, without Codex on the server?
3. Does it leave an **audit trail** suitable for "who matched this deposit?"
4. Can an **AI agent** perform the same action through OpenAPI/MCP?
5. Does it stay **out of full accounting** territory unless the expansion roadmap says otherwise?
6. Does it **register terms** before shipping identifiers?

If most answers are no, reconsider or split the work.

---

## 4. What we refuse to become

| We are not | Why |
| --- | --- |
| A general ledger | freee / MF territory; we export CSV instead |
| A bank | We import CSV; we do not hold deposits |
| A payment gateway (Phase 1–3 core) | Manual record first; PSP optional later |
| A WordPress plugin | Sibling app on same origin is fine |
| An AI chatbot | MCP proposes; UI and logs confirm |
| A debt collection agency | Dunning is operator-controlled professional reminder |

---

## 5. Name — why *Clear*

| Reading | Meaning |
| --- | --- |
| **Clear (verb)** | 消込 — reconcile bank lines to invoices |
| **Clear (adjective)** | 明快 — transparent status: draft, issued, overdue, paid |
| **Clear (verb)** | クリア — remove Excel chaos; one system of record |

One word, three readings — same as Records / Corpus / Concierge in the NeNe
family.

---

## 6. Relationship to the NeNe portfolio

```
Website (WordPress / static)
    ├── NeNe Corpus      — visitor asks questions (public knowledge)
    ├── NeNe Concierge   — visitor converts (scenario chat)
    └── NeNe Records     — content & catalog (CMS)

Back office (same server or VPS)
    └── NeNe Clear       — quote, bill, collect, clear (this product)
```

**Front office** attracts and informs. **Clear** closes the commercial loop.

---

## 7. Document map

| Question | Read |
| --- | --- |
| Why does the product exist? | This file + `product-vision.md` |
| What do we build in v1? | `requirements.md` |
| What comes after MVP? | `expansion-roadmap.md` |
| What is the product called? | ADR 0007 — **NeNe Clear** |
| What are exact spellings? | `terminology.md` |

---

## 8. Maintainer intent (理念 — 日本語)

> **見積から入金までを、Excel と記憶から解放する。**
>
> 適格請求書は「PDF の体裁」ではなく、API が守るルール。データは自分のレンタルサーバーに置き、SaaS 月額を増やさない。AI 時代だからこそ、人間が確定し、AI が提案し、記録が残る — その三つを同時に満たすバックオフィス OSS。
>
> 名前 **Clear** は「消込」と「明快」の両方。請求書 PDF だけのツールにはならない。

---

## Related

- ADR 0007: Product identity
- [`product-vision.md`](./product-vision.md)
- [`expansion-roadmap.md`](./expansion-roadmap.md)
- [`accounting-compliance.md`](./accounting-compliance.md)

Last updated: 2026-05-29 (Issue #31)
