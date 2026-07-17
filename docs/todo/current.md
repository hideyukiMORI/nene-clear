# Current Work

Last updated: 2026-07-18

> **2026-07-18: A2 (FSD 5-layer rebuild) — shared foundation essentially done.**
> After Tier1 (`shared/lib` #359 / `shared/i18n` #360 / `shared/api` #362),
> **Tier2 `shared/ui`** landed (design #369 / impl #370): the 15 `components/ui`
> components were exploded into `shared/ui/<kebab>/` (aggregate barrel removed,
> 01:126), `ThemeContext` → `shared/ui/theme`, and the 14 importers + `main.tsx`
> re-pointed. **AppShell → `app/layout/AppLayout`** (#371). Every step was a
> behavior-preserving self-merge with a **logic-diff-0 mechanical proof**; CI all
> green (incl. e2e). 🔴 **`components/keyboard` is frozen**: its placement is
> undecided by the `01` §4 decision tree (4 of its 10 files are React UI `.tsx`),
> so per the rule it must not be parked in `shared/lib` — filed as a spec defect,
> **fleet-tooling#89**. It is the only remaining resident of `src/components/`.
> **Next = the entities layer** (the main body; absorbs #317 Phase 3 type
> re-point) from its design gate. Details:
> [`../daily/2026-07-18.md`](../daily/2026-07-18.md). CI: `main` is locked
> (enforce_admins); required checks =
> `[backend-check, frontend-check, postgres-migrate]` (#367).

> **2026-07-11: fleet structural-uniformity audit recorded** — Clear findings,
> strengths, and tracking issues (#285 JWT stack, #286 NENE2 `@dev` dependency,
> #287 checklist) in
> [`docs/audit/2026-07-11-structural-uniformity.md`](../audit/2026-07-11-structural-uniformity.md) (#288).

> **2026-07-11: audit remediation — complete (2 stop-gates planned).**
> Backend merged earlier: #286 NENE2 → Packagist `^1.10` + hardened release
> build (#290); #285 JWT → fleet-standard NENE2 stack (#291); #287 checklist —
> MFA core (#293), ConfigLoader/AppConfig + 48-line front controller (#295),
> branded demo error page (#297), OpenAPI/MCP `composer check` gates (#299),
> tenant scoping via injected `CurrentOrganization` (#300, #301 / PR #302).
> **(j) installer self-delete (#308).** **(i) frontend divergence:** font
> dedup (#309), `knip` + `--max-warnings 0` (#310), a real `type-check`
> (`tsc -b`) + the 13 latent errors it had hidden (#311), React-connected
> i18n → `I18nProvider`/`useTranslation` (#316). **OpenAPI codegen** is a
> **stop-gate — plan #317** (the spec must be reconciled first; the divergences
> were re-verified against the code on 2026-07-16 —
> [`../audit/2026-07-16-openapi-spec-drift.md`](../audit/2026-07-16-openapi-spec-drift.md),
> which refutes several of #317's claims and finds 7 shipped endpoints the spec
> omits). The `client_credit.status` drift is **now fully resolved**: #319
> corrected the registry + spec, and **#340** corrected the artifact #319 missed
> (`docs/mcp/tools.json`, which had kept advertising the retired values).
> **#264 itself stays open on purpose** — it now tracks only an optional future
> enhancement (a richer `partially_applied` / `applied` lifecycle); it is not a
> leftover to close. **FSD migration** is a **stop-gate — plan #318**. Also
> fixed 2 production sales-blockers from
> the browser walkthrough: X-Authorization mirror on file I/O (#312 / #313) and
> the no-op settings-save 500 (#314 / #315).

> **Latest: demo enablement shipped (#260–#262, 2026-07-09)** — one-command
> T-relative demo seeder (`tools/seed-demo.php`), env-gated demo upstream
> fixture (`NENE_CLEAR_DEMO_UPSTREAM=1`), and the demo runbook
> **[`docs/demo.md`](../demo.md)**. Two real product bugs found and fixed on
> the way: the bank CSV parser stored yen ×1 while everything else is ¥1=100
> (#261), and JSON POST bodies were never parsed at the entry point, so the
> SPA's propose/confirm/dunning-send failed against the real backend (#262).
> The 2026-07-03 screenshot session's `.env` (dead stub on 8390) has been
> reverted. **The demo is live at `https://clear.ayane.co.jp` (deployed
> 2026-07-09 evening, owner GO)** — HETEML, MySQL `_nene_clear`, invoice-shaped
> docroot; the deployment surfaced and fixed two more Tier A bugs (#265
> Authorization stripped by the front proxy, #268 SPA asset base).
> **2026-07-10: the disposable-org demo is live too** (`/demo/standard`,
> `Nene2\Demo` v1.9.0 consumer, #275; MySQL LIKE-escape fix #277) — hand out
> `https://clear.ayane.co.jp/demo/standard` and every visitor gets a fresh
> throttled, TTL-swept org. Remaining owner steps (HETEML panel, cron):
> nightly fixed-org reset `~/bin/reseed-clear-demo.sh` and hourly demo sweep
> `~/bin/sweep-clear-demo.sh`. SMTP is unset (dunning channel `log`), and the
> `client_credit.status` registry/enum drift is #264. Details:
> [`docs/journal/2026-07-09.md`](../journal/2026-07-09.md) and
> [`docs/journal/2026-07-10.md`](../journal/2026-07-10.md).
> Qiita shots 3, 4, 5, 7 are still open
> ([handoff-2026-07-03](handoff-2026-07-03.md)); dev work otherwise resumes at
> **installer Slice 3** ([handoff-2026-07-02 §3](handoff-2026-07-02.md)).

## Status

**Phase 1 — Complete** (payment reconciliation API).
**Phase 2 — Complete** (Admin UI + dunning; ja/en; professional review gate obtained).
**Infrastructure — Complete** (docker-compose + Mailpit; SmtpDunningMailer; InvoiceUpstreamHttpClient; login throttle).
**Security — Assessed** (2-round multi-tenant pentest; 4 findings fixed incl. one critical privilege escalation; `docs/security/assessment-2026-05.md`).
**Post-Phase-2 hardening — Merged** (PR #99–#118): bug fixes, 6 frontend gap closures, CSV export, per-invoice dunning pause, `template_version`, OpenAPI Dunning/Export spec, NENE2-compliant DI + soft-delete + full audit trail, admin audit-log page, shared `StatusBadge`/`Pager`, ClaudeDesign design system, E2E realign + a11y.
**nene-invoice upstream — Verified live once (2026-06-27, `28bb88b`): all 6 contract tests passed against a running Invoice and surfaced 2 real contract bugs (#214). No *deployed* environment is configured against a live Invoice yet, so the tests auto-skip in CI.**

### Tests / quality gates

Counts measured 2026-07-16 by the runners themselves (not by `grep`).

| Layer | Count | Tool |
| --- | --- | --- |
| Backend | 461 (6 skipped; 7375 assertions) | PHPUnit; PHPStan level 8; PHP-CS-Fixer |
| Frontend unit | 62 (9 files) | Vitest |
| Browser E2E | 54 (16 files) | Playwright (API mocked) |

CI: GitHub Actions — `backend-ci`, `frontend-ci`, `e2e-ci` run on push/PR to main;
`close-issues` auto-closes linked Issues on rebase-merge to main.
6 skipped backend tests = Invoice contract tests that auto-activate once
`NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` are set.

## What is running

| Domain | Endpoints | Auth |
| --- | --- | --- |
| Auth | `POST /admin/auth/login` (throttled), `GET /admin/auth/me` | JWT |
| Organization | `GET/POST /admin/organizations`, `GET/DELETE /admin/organizations/{id}` | `manage_organizations` |
| User | `GET/POST /admin/users`, `GET/PUT/DELETE /admin/users/{id}` | `manage_users` |
| ClearSettings | `GET/PUT /admin/clear-settings`, `POST /admin/clear-settings/test-upstream` | `manage_clear_settings` |
| Bank import | `POST /admin/bank-import-batches` (CSV), `GET` list, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Bank transactions | `GET /admin/bank-transactions` (filters), `GET /{id}`, `GET /unmatched` | `view_reconciliation` |
| Invoice upstream | `GET /admin/upstream/invoices` (read-only proxy) | `view_reconciliation` |
| Reconciliation | `POST /admin/reconciliations/propose`, `POST /admin/reconciliations` (confirm), `GET` list/by-id, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Client credits | `GET /admin/client-credits`, `POST /{id}/apply` | `view_reconciliation` / `manage_reconciliation` |
| Dunning | `POST /admin/dunning-notices`, `GET` list/by-id | `send_dunning` / `view_reconciliation` |
| Dunning pause | `GET/POST /admin/dunning-pauses`, `POST /{invoiceId}/resume` | `send_dunning` / `view_reconciliation` |
| Export (CSV) | `GET /admin/export/{reconciliations,client-credits,bank-transactions}` | `view_reconciliation` |
| Audit log | `GET /admin/audit-events` | `manage_users` |
| Admin UI | React SPA (`frontend/`), served from `public_html/assets/` | JWT (session storage) |

## Infrastructure

Fixed local ports (see `CLAUDE.md`; do **not** revert to framework defaults).

| Component | Status | Notes |
| --- | --- | --- |
| Docker | ✅ `docker-compose.yml` | MySQL 8.4 (host port 3383) + Mailpit (SMTP 1383, web UI 8383) |
| PHP backend | ✅ | `NENE_CLEAR_PORT=8384` |
| Vite dev server | ✅ | `NENE_CLEAR_FRONTEND_PORT=5383` |
| SMTP mailer | ✅ `SmtpDunningMailer` | Activated when `SMTP_HOST` set; falls back to `LogOnlyDunningMailer`; credentials passed via EsmtpTransport (not DSN) |
| Invoice upstream | ✅ `InvoiceUpstreamHttpClient` | Activated when `NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` set; falls back to `FakeInvoiceUpstreamClient` |
| Login throttle | ✅ `PdoLoginThrottle` | 5 failures / 15 min per email+IP → 15 min lock (HTTP 429) |
| Contract tests | ✅ 6 tests (auto-skip) | Run against real Invoice API by setting env vars |
| CI | ✅ GitHub Actions | backend / frontend / e2e / close-issues workflows |

## Open risks

1. **No standing connection to a live Invoice** — *narrower than it used to read.*
   The integration **has** been exercised end-to-end: `28bb88b` (2026-06-27)
   records all 6 InvoiceUpstream contract tests passing **against a live Invoice**
   (63 assertions), and the two contract fixes in #214 were found *by* that run.
   So "does this work at all" is answered. What remains is operational: no
   deployed environment has `NENE_INVOICE_API_BASE_URL` /
   `NENE_INVOICE_BEARER_TOKEN` set against a real Invoice instance, so the 6
   contract tests auto-skip in CI and the pairing is unproven outside a
   developer machine. Re-run them whenever the upstream contract changes.

## Next steps

**Adoption Readiness milestone — nearly complete**
([`../milestones/2026-06-adoption-readiness.md`](../milestones/2026-06-adoption-readiness.md);
from the 2026-06 adoption review #190,
[`../explanation/adoption-review-2026-06.md`](../explanation/adoption-review-2026-06.md)).
Shipped: ✅ UI polish (#196, PR #200) · ✅ account-number masking (#192, PR #201) ·
✅ standalone receivables — CTA / import aliases / repositioning (#191, PR #202–#204) ·
✅ dunning hardening — deliverability / preview / test-send / staged templates
(#194, PR #205/#208/#209/#210) · ✅ encryption-at-rest (#195, PR #207) ·
✅ retract Tier A (#193, PR #206).

> **Full project handoff:** [`handoff-2026-06-28.md`](handoff-2026-06-28.md)
> (snapshot, MFA detail, risks, prioritized TODO).

**MFA (#195) — backend complete; frontend + CLI remain.** Standalone TOTP, auth
decoupled from federation (Suite = IdP; nene-suite#341, ADR 0025; NENE2
`totp-authentication` recipe). Secret encrypted via the #207 `Encryptor`; recovery
codes hashed.

1. ✅ **Slice 1 — TOTP foundation** (PR #213): RFC 6238 generator, encrypted
   storage, lockout + replay, recovery codes.
2. ✅ **Slice 2 — enrolment endpoints** (PR #216): setup / enable / status / disable.
3. ✅ **Slice 3 — login integration** (PR #217): password → challenge → verify → token.
4. ⬜ **Slice 4 — frontend + break-glass CLI**: enrol (QR + recovery codes) and
   login-challenge screens; an audited CLI to disable a user's MFA for lockout
   recovery. **Do not enable MFA on a sole-admin deployment until the CLI exists.**

**Minor follow-ups** (open on their issues): real dashboard KPI-sub aggregates +
term tooltips (#196); server-side account-number withholding (#192); column-mapping
UI (#191); dunning stage persistence + ADR 0011 tone review (#194).

**Then resume prior roadmap:**
2. **Activate real Invoice upstream** — set the env vars in a *deployed*
   environment and keep the contract suite green there. (The suite already
   passed against a live Invoice on 2026-06-27; what is missing is a standing
   pairing, not a first proof.)
3. **CSV export — tax advisor sign-off** — review column set per compliance §9.
4. **Phase 4 — Ecosystem** — MCP tools (`listUnmatchedTransactions`,
   `proposeMatch`, `sendDunningNotice`).

Business/owner levers (not code; see review doc): managed/SaaS supply, pricing
(A/B/C), tax-advisor MSP channel. Roadmap **Phase 3** was reframed to
"Distribution (managed / install-service)" per #193 (PR #206).

## Recently completed

- **Since 2026-07-11** (these landed after this file's previous update):
  - **#331** — demo entry logging moved from `error_log` to a `var/` file sink
    (readable over SSH on shared hosting).
  - **#333** — the shared `apiClient` adopts the `@hideyukimori/nene2-client`
    transport.
  - **#335** — `design.css` isolated into `@layer legacy` + a machine-generated
    manifest (W1 pilot for the fleet style work).
  - **#337** — the X-Authorization fallback receiver switched to NENE2's opt-in
    flag (`enableAuthorizationHeaderFallback`), retiring Clear's own
    implementation; `hideyukimori/nene2` → `^1.11`.
  - **#339** — `.gitignore`: `.claude/worktrees/` + `.claude/settings.local.json`
    (the latter had been protected only by a developer's *global* ignore, so the
    public repo had no such protection for anyone else).
  - **#340** — `client_credit.status`: removed the retired enum from
    `docs/mcp/tools.json` and the phase-1 design doc. This closed the gap #319
    left (see the header note); `?status=applied` had been silently returning
    *all* credits through the MCP surface, because `ClientCreditFilter` resolves
    status with `tryFrom`.
- **Candidate-database preflight adopted** (#183): opt-in
  `POST /machine/database/preflight` is live — `DefaultDatabaseCandidateInspector`
  wired with this app's Phinx versions (`MigrationVersions`), the **`phinxlog`**
  ledger name, and `ApplicationDatabaseIdentity` (`nene-clear`, single-tenant DB
  lineage). Migration `20260703000000` stamps / backfills the
  `nene2_app_identity` marker idempotently. Candidates come only from the
  `NENE_CLEAR_DB_CANDIDATE_*` env allowlist (SSRF-safe; documented in
  `.env.example`). Tests cover fresh / migrated-own-DB (compatible + identity
  match) / unknown-id 422 / key gating / endpoint absent without the inspector.
- **Machine surface: installed version on `GET /machine/health`** (#182): the
  repo `VERSION` is injected as `appVersion` into both `RuntimeApplicationFactory`
  sites (health-only and full), `NENE2_MACHINE_API_KEY` gates the machine surface
  (`.env.example` documented; NeNe Suite pairs it as
  `NENE_SUITE_APP_NENE_CLEAR_MACHINE_KEY`), and the public `/health` stays
  version-free. HTTP tests cover key gating and version reporting.
- **MFA (TOTP) backend** (slices 1–3, PR #213 / #216 / #217): RFC 6238 generator,
  encrypted secret + hashed recovery codes, lockout + replay, self-service
  enrolment endpoints, and a login challenge for enrolled users. Frontend +
  break-glass CLI remain (slice 4 of #195).
- **Adoption Readiness milestone** (#191 / #192 / #193 / #194 / #196 + encryption
  half of #195): PR #200–#210 — see
  [`../milestones/2026-06-adoption-readiness.md`](../milestones/2026-06-adoption-readiness.md).
- 2026-06 adoption review (10-persona simulation): findings doc
  [`../explanation/adoption-review-2026-06.md`](../explanation/adoption-review-2026-06.md)
  (#190) + follow-up issues #191–#196, scheduled in the Adoption Readiness milestone.
- Dev tooling: one-command `/dev-up` via `.claude/skills/run-local/run-local.sh`
  (php -S + SQLite + Vite + Mailpit); public-repo wording fixes.
- Post-Phase-2 hardening merged to main (PR #99–#118): see Status above.
- i18n message catalog (ja/en), multi-tenant SQL scoping, audit before/after
  snapshots, reconciliation idempotency, operator guide (電子帳簿保存法 §3.4).
- Admin UI (login, dashboard, bank import, transactions, reconciliation,
  dunning, client credits, settings, users, audit log) with full test coverage.
- Security hardening: login rate limiting, role-assignment privilege-escalation
  guard, SMTP credential handling, upstream error masking.
- CI pipelines (backend / frontend / e2e / auto-close-issues).
