# ADR 0011: Dunning is Self-Collection Support Only; No Automatic Late Interest

## Status

accepted

> Engineering's interpretation of collection-law boundaries — **not legal
> advice**. Confirm with a 弁護士 before relying on it; statutory values must be
> verified at implementation.

## Context

NeNe Clear sends overdue payment reminders (督促). Collection touches several
legal limits that a reviewing professional (税理士 / 弁護士) will check:

- **弁護士法72条 / Servicer Act (債権管理回収業に関する特別措置法):** collecting
  **others'** debts for a fee, as a business, is reserved for licensed attorneys
  or licensed servicers (債権回収会社). Reminding your **own** customers about
  **your own** invoices (self-collection) is lawful.
- **Tone:** coercive, threatening, or false statements ("we will sue
  immediately") are improper collection conduct.
- **Late interest (遅延損害金):** B2B debts may carry interest at a contractual or
  statutory rate (民法404 statutory rate, currently 3%, reviewed every 3 years).
  Auto-computing it on the wrong basis, or presenting it as a legal demand,
  produces an inaccurate claim and a coercive impression.
- **Over-frequency** reminders can themselves read as harassment, and reminding a
  customer who has actually paid (stale data) damages the relationship.

## Decision

NeNe Clear's dunning is **support for the operator's self-collection of their own
receivables only**, within these binding limits:

1. **Self-collection only.** Clear MUST NOT collect third-party debts for a fee
   or present such a feature, and MUST NOT impersonate or imply a lawyer,
   collection agency, or court (弁護士法72条 / サービサー法). 督促 (reminder) is
   distinct from 取立 (enforcement); Clear does only the former.
2. **No automatic late interest on the balance.** Clear MUST NOT auto-compute
   遅延損害金 and add it to the outstanding balance, and MUST NOT present interest
   as a legal demand by default. An **optional, off-by-default** template
   placeholder for contractually agreed / statutory interest MAY exist; enabling
   it requires advisor sign-off. The operator owns correctness of any interest
   claim.
3. **Operator control + frequency guard.** Default is **manual** send per
   invoice. Scheduled sending is **org opt-in** and MUST respect a **minimum
   interval** (default 7 days) per invoice, and MUST reflect the latest
   reconciliation state at send time (no reminding a paid invoice).
4. **No coercive or false content** in default templates (§4.4 of the compliance
   doc). Each send is logged immutably.

Crossing the self-collection boundary (e.g. an agency offering) is a **separate
product** requiring legal review and a new ADR — not an incremental feature.

## Consequences

**Benefits**

- Keeps the product on the lawful side of 弁護士法72条 / サービサー法.
- Reminders stay accurate (no mis-computed interest, no stale-data sends).
- Clear story for reviewers: a reminder tool, not a collection agency.

**Costs**

- Operators wanting automatic interest calculation must enable an opt-in feature
  with advisor sign-off, or compute it themselves.
- No fully-automated aggressive collection workflow (by design).

**Follow-up**

- 弁護士 review of default template wording before Phase 2 dunning ships
  (compliance §9 gate).

## Related

- Compliance: [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) §4 (esp. §4.4, §4.5, §4.8)
- Scope contract: [`../explanation/scope-contract.md`](../explanation/scope-contract.md) (X8, X9, X10)
- Personal data handling: compliance §4.6
- Supersedes: none
- Superseded by: none
