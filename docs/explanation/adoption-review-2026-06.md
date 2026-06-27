# Adoption Review — 2026-06 (10-persona simulation)

> **Status: findings record (point-in-time).** Tracked by issue #190.
> **This is an AI-simulated persona review, not real user research.** Treat it as
> directional signal for prioritization, not validated demand. Reconciliation
> accuracy, freee/弥生 output fit, and live deliverability still need testing
> against real data and real users.

## Method

Ten simulated Japan-SMB decision-making accounting personas — varied by industry,
role, background, current billing/accounting tool (freee / 弥生 / マネーフォワード /
MISOCA / 商奉行 / NeNe Invoice / Square) and hosting (sakura / heteml / Xserver /
lolipop / on-prem / VPS / Wix) — each **viewed real screenshots of the live admin
UI** and walked the normal-flow scenario (connect Invoice API → import bank CSV →
review unmatched → propose/confirm a match incl. partial/overpayment → send
dunning → check the audit log → CSV export), then gave a verdict. They then held a
six-round round-table, and the result was synthesized into adopt-levers and
pay-levers.

## Headline

**The product core and UI are validated by everyone, including the pass camp. What
blocks adoption is the distribution / connectivity / operations model — not missing
features.** "Finishing touches" here means go-to-market plumbing, not more product.

| Verdict | Count |
| --- | --- |
| Adopt | **0** |
| Consider | **3** (food-wholesaler on NeNe Invoice; tax advisor; freelance dev — dunning-only) |
| Pass | **7** |

Willingness to pay attaches to **operations + connectivity + support**, not to the
MIT-free binary.

## Validated core (keep — do not "fix" these)

- **Immutable audit trail** (who/when/what, before→after). Rated the single most
  valuable thing; "impossible to build in paper + Excel."
- **Safety-railed dunning** (send history, minimum-interval guard, pause/resume,
  pre-send confirm modal) — directly replaces ad-hoc manual reminders and lowers
  the sender's psychological barrier.
- **Human-confirmed reconciliation** (candidate → confirm). Endorsed across the
  automation, audit, and conservative camps as the right internal control.
- **Partial payment ("一部消込") and overpayment ("前受金/適用待ち") as first-class
  concepts** — the area Excel handles worst. Even the minimalists kept it.
- **Dashboard KPIs and overall UI/UX completeness** — "the screens are clear" was
  unanimous, from IT-averse to busy small shops.
- **Honest scope disclaimer** (no warranty; tax/legal sign-off is the operator's) —
  keep it, and *complement* it with paid support/SLA rather than removing it.

## Three structural blockers (why adopt = 0)

1. **Receivables entry point (perceived).** Nine of ten don't use NeNe Invoice and
   read the product as requiring it. **Correction:** standalone operation already
   exists — `importManualReceivables` (bulk CSV import of `ManualReceivable`,
   [ADR 0014](../adr/0014-accept-manual-receivables.md)) is implemented. The real
   gap is **discoverability + per-tool import presets**, not the import itself
   (#191).
2. **Self-host-OSS-only distribution.** "Free, but the TCO is not free" was the
   consensus. Even the personas who *can* run a VPS won't, without managed /
   install-service / support. Value lives in operations hand-off, not the binary
   (#193, and the supply-side note below).
3. **Responsibility + security gaps.** Plaintext bank account number in the UI
   (#192), no MFA / unclear encryption-at-rest (#195), and a shared-hosting
   recommendation inappropriate for financial data (#193). Honesty ≠ safety for
   regulated financial/receivables data.

## Adopt-levers (priority)

| Priority | Lever | Issue |
| --- | --- | --- |
| **P0** | Make the standalone (manual-receivable) path first-class + add billing-tool CSV import presets (freee/MF/弥生/MISOCA) | #191 |
| **P0** | Operations hand-off: managed / install-service / support (the binary stays free) | #193 + supply note |
| **P0** | Paid support / SLA + responsibility boundary + reference deployments | (business model) |
| **P1** | Security: mask account number + reveal-audit; MFA; encryption-at-rest | #192, #195 |
| **P1** | Dunning to practical grade: staged templates, preview/test send, deliverability, do-not-send mode | #194 |
| **P1** | Retract Tier A (shared hosting) recommendation; recommend Tier B + managed | #193 |
| **P2** | B2C / relationship businesses: name-matching dictionary, one-payment→many-receivables, "don't send" dunning | (later) |
| **P2** | Allocation evidence + 電子帳簿保存法 posture (reversal/re-allocation before→after in the audit log) | (later) |

## Pay-levers (layered — a single price does not exist)

Net-new value flips sign with the customer's existing tool, so price must be
layered:

| Tier | Audience | Model | Price hint |
| --- | --- | --- | --- |
| **A. Thin SaaS** (dunning + audit only) | freee / MF shops (feature overlap → low ceiling) | low-price subscription | ~¥1,000–3,000 / mo |
| **B. Full managed** (connectors + install + support) | 弥生 / manual-entry / dunning-by-hand shops — **the prime segment** | binary free, ops paid | ~¥1–3万 / mo |
| **C. Enterprise** (SLA, references, on-prem) | mid-size finance (e.g. on-prem 奉行 shops) | paid maintenance + onboarding | ~¥2–3万/mo + ¥20–50万 setup |
| Install-service + mapping setup | converts conditional-WTP personas immediately | one-off + optional maintenance | ¥5–15万 + ¥5,000–15,000/mo |
| Tax-advisor MSP (two-tier) | advisor-served SMBs | advisor subscription + upstream SLA | ~¥6–12万 / client / yr |

> **The biggest unresolved issue is the supply gap.** Conditional WTP exists
> (e.g. the prime persona would pay ¥1–3万/mo) but, with an OSS/solo-maintainer
> assumption, *who sells the SLA / patches / support* is undefined. Closing that —
> an official managed offering and/or a tax-advisor MSP channel — is the real
> monetization work.

## Reframe: what "finishing" means

The team viewed the product as near feature-complete. The review agrees the
**features and UI are done well** — and points the next investment at
distribution, not features:

1. Make standalone CSV-receivable operation discoverable + add import presets (#191).
2. Stand up an official managed / install-service offering (close the supply gap).
3. Security hardening (#192, #195) + retract the shared-hosting recommendation (#193).
4. Dunning to practical grade (#194).
5. Progressive-disclosure + role-separated UI; annotate (don't rename) bookkeeping
   terms; neutralize sample data (#196).
6. Allocation evidence + 電子帳簿保存法 documentation (unlocks the enterprise / advisor channels).

## Cut candidates (don't add — remove)

- Shared hosting (Tier A) as a *recommended* target for this data (#193).
- Plaintext account-number display (#192).
- Renaming bookkeeping terms (消込/突合/前受金/充当) — annotate instead (#196).
- Pushing "manual-receivable standalone" as a *re-keying* path — make it an import
  flow, not a data-entry chore (#191).
- B2B-only sample data that signals "not for me" (#196).

## Persona verdicts

| Persona (role / tool) | Verdict | Note |
| --- | --- | --- |
| 田中 (food wholesale / NeNe Invoice) | Consider | Only positive fit; depends on NeNe Invoice, conditions itemized |
| 渡辺 (tax advisor / 50 clients) | Consider | Became the proposer of the MSP supply model |
| 松田 (dev contractor / freee) | Consider | Values dunning standalone; WTP cap ~¥1,000, self-hosts |
| 佐藤 (manufacturing / 弥生) | Pass (conditional) | "If the advisor is the front desk, ¥1–2万 passes internal approval" |
| 山本 (CFO / freee) | Pass | freee auto-recon overlap; net value = dunning + audit only |
| 鈴木 (design / MF) | Pass | No MF adapter; needs login-only SaaS |
| 高橋 (construction / 弥生 + manual) | Pass | High value but can't self-host; no support |
| 中村 (cram school / MISOCA, B2C fees) | Pass | Same-amount / name-mismatch fees fall outside matching |
| 伊藤 (wholesale exec / 奉行) | Pass | Design trust + SLA + track record before price |
| 小林 (salon / Square) | Pass | Almost no receivables — the problem doesn't exist for them |

## Related

- Product vision: [`product-vision.md`](./product-vision.md)
- Scope contract: [`scope-contract.md`](./scope-contract.md)
- Standalone receivables: [ADR 0014](../adr/0014-accept-manual-receivables.md)
- Roadmap: [`../roadmap.md`](../roadmap.md)
- Follow-up issues: #191 #192 #193 #194 #195 #196 (tracked by #190)
