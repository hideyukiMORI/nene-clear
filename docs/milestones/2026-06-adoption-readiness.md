# Milestone: Adoption Readiness (2026-06)

Goal: turn the 2026-06 adoption review (#190,
[`../explanation/adoption-review-2026-06.md`](../explanation/adoption-review-2026-06.md))
into shipped changes that remove the **structural adoption blockers** —
without touching the validated core (immutable audit trail, safety-railed
dunning, human-confirmed reconciliation, first-class partial/overpayment, UI).

**Status: nearly complete (2026-06-27)** — every item shipped except the MFA
half of #195, which is now unblocked (auth is federated; see below) and in
progress.

The review's verdict was adopt 0 / consider 3 / pass 7: features and UI are
validated, but distribution / connectivity / operations block adoption. This
milestone is the engineering-ownable slice of the fix.

## Acceptance Criteria

Execution order (quick, high-confidence first; P0 levers interleaved):

- [x] **UI polish (#196)** — audit-log label i18n + dashboard subtitle fixed
      (PR #200). Remaining on #196: real KPI-sub aggregates, term tooltips.
- [x] **Mask bank account number (#192)** — UI masking + reveal toggle +
      audit-snapshot masking (PR #201). Server-side withholding + an audited
      reveal endpoint are deferred to the encryption track (noted on #192).
- [x] **Standalone receivables, first-class (#191, P0)** — discoverability CTA
      (PR #202), alias-aware CSV import incl. Shift-JIS / ¥ amounts (PR #203),
      repositioning copy (PR #204). Optional column-mapping UI remains (on #191).
- [x] **Dunning hardening (#194)** — deliverability guide (PR #205), body preview
      (PR #208), test send (PR #209), staged templates (PR #210). "Do-not-send"
      is covered by the existing dunning pause; minor follow-ups remain (stage
      persistence, ADR 0011 tone review).
- [~] **Security (#195)** — encryption-at-rest for the bank account number done
      (libsodium, PR #207). **MFA is unblocked**: auth is federated (Suite = IdP)
      and MFA/TOTP is a NENE2-generic capability — see nene-suite#341 / ADR 0025.
      Clear now builds **standalone TOTP MFA** (NENE2 `totp-authentication`
      recipe; enroll = user / enforce = deployment operator policy; mandatory
      recovery codes; secret encrypted via the #207 Encryptor; break-glass via an
      audited CLI). **In progress.**
- [x] **Retract Tier A recommendation (#193)** — done (PR #206); roadmap Phase 3
      reframed to "Distribution (managed / install-service)".

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
