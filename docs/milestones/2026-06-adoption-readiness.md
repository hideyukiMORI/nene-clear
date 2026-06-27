# Milestone: Adoption Readiness (2026-06)

Goal: turn the 2026-06 adoption review (#190,
[`../explanation/adoption-review-2026-06.md`](../explanation/adoption-review-2026-06.md))
into shipped changes that remove the **structural adoption blockers** —
without touching the validated core (immutable audit trail, safety-railed
dunning, human-confirmed reconciliation, first-class partial/overpayment, UI).

**Status: in progress (started 2026-06-27)**

The review's verdict was adopt 0 / consider 3 / pass 7: features and UI are
validated, but distribution / connectivity / operations block adoption. This
milestone is the engineering-ownable slice of the fix.

## Acceptance Criteria

Execution order (quick, high-confidence first; P0 levers interleaved):

- [ ] **UI polish (#196)** — localize all audit-log event-type labels (no raw
      slugs like `clear_settings_updated`), resolve the dashboard
      "残高合計取得中" state, neutralize B2B-only sample data, add tooltips for
      bookkeeping terms (annotate, do not rename).
- [ ] **Mask bank account number (#192)** — mask `account_number` in the UI;
      reveal-on-demand; record the reveal as a (newly registered) audit event.
- [ ] **Standalone receivables, first-class (#191, P0)** — surface the existing
      `importManualReceivables` path in onboarding/empty states; add per-tool
      CSV import presets (freee / MF / 弥生 / MISOCA); reposition copy so Clear
      reads as usable without NeNe Invoice.
- [ ] **Dunning hardening (#194)** — staged templates (initial/reminder/final),
      body preview + test send, deliverability (SPF/DKIM/DMARC) guidance,
      do-not-send / contact-only mode. Stay within the self-collection boundary
      (ADR 0011).
- [ ] **Security (#195)** — MFA (TOTP) for admin login; encryption-at-rest for
      sensitive fields (implement and/or document).
- [ ] **Retract Tier A recommendation (#193)** — stop recommending shared
      hosting for this data; recommend Tier B (VPS+Docker) + install-service /
      managed. **Product-owner sign-off required** (intersects roadmap Phase 3).

## Out of scope (business / owner, tracked in the review doc — not this milestone)

- Official **managed / SaaS** offering and the **support-supply** model.
- **Pricing** (layered A/B/C) and the tax-advisor **MSP channel**.

These are the largest pay-levers but are not code tasks; they need a
product-owner decision (see the review doc's pay-levers + "supply gap").

## Definition of done

- Each in-scope issue (#191, #192, #194, #195, #196) merged with green
  `composer check` + frontend `check` + E2E, and any new identifiers registered
  in [`../explanation/terminology.md`](../explanation/terminology.md) first.
- #193 reflected in README / product-vision / roadmap once the owner signs off.

## Follow-up

After this milestone, resume the prior roadmap: activate the real Invoice
upstream (live contract tests), Phase 4 ecosystem (MCP tools), and the CSV-export
tax-advisor sign-off. See [`../roadmap.md`](../roadmap.md).
