# Scheduled dunning — implementation design (design gate)

**Status: design (pre-implementation), hub-ruled.** One item stays open for the owner: whether
`final`-stage notices may be sent unattended (§5). Everything else is settled. Engineering design for
[#400](https://github.com/hideyukiMORI/nene-clear/issues/400) — sending dunning notices
automatically once an invoice is overdue, instead of an operator pressing send.

It instantiates the binding rules; on any conflict the rules win:
[`scope-contract.md`](../explanation/scope-contract.md),
[`payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md),
[ADR 0011](../adr/0011-dunning-self-collection-only.md) (self-collection only; tone / escalation),
[`terminology.md`](../explanation/terminology.md) (identifiers must be registered before first
use), [`nene2-compliance.md`](./nene2-compliance.md).

See also: [`phase-1-reconciliation-design.md`](./phase-1-reconciliation-design.md).

---

## 1. Goal & non-goals

**Goal.** An organization can opt in to having Clear send its overdue dunning notices on a
schedule, with the same guards, records and audit trail as a manual send.

**Non-goals.**

- No change to *what* a dunning notice says. Templates, tone and escalation stay exactly as
  ADR 0011 defines them; this feature only decides *when* `SendDunningUseCase` is called.
- No collection actions beyond self-collection (scope X10 — no threats, no third-party
  collection, no credit reporting).
- No change to the manual send path. Operators keep the button.

## 2. Ground truth (measured 2026-07-29)

The send path is already safe. What is missing is only a caller.

`src/Dunning/SendDunningUseCase.php` already enforces, in this order:

| # | Guard | Failure |
| --- | --- | --- |
| 1 | Upstream invoice is `issued` / `partially_paid` / `overdue` **and** `outstanding_cents > 0` | `InvoiceAlreadyPaidException` |
| 2 | No active row in `dunning_pauses` for the invoice | `DunningPausedException` |
| 3 | `now >= last_sent_at + clear_settings.dunning_min_interval_days` (default 7) | `DunningTooFrequentException` |
| 4 | Notice row + `dunning_sent` audit event are committed **in one transaction, before** the mail is handed to the mailer (#122) — a delivery failure leaves an honest "attempted" trail | — |
| 5 | Time comes from an injected `Nene2\Http\ClockInterface` | — (makes this testable) |

Guard 5 is why the acceptance criteria below can be met by unit tests with a frozen clock
rather than by waiting for real dates.

Also measured: `clear_settings` currently carries exactly one dunning column
(`dunning_min_interval_days`); `dunning_pauses` records `paused_by` / `paused_at` /
`paused_reason` and an unpause pair.

## 3. Trigger — OS cron running a one-shot CLI (hub ruling D1)

```
# production crontab (owner-installed, once)
*/15 * * * * cd /path/to/app && php tools/send-scheduled-dunning.php >> var/log/dunning-cron.log 2>&1
```

- **One-shot, not a daemon.** Production is shared hosting (HETEML); a resident worker is not
  operable there. `tools/sweep-demo.php` is the in-repo precedent for "thin entry point, run
  from cron" (#275), including how it wires the DB at the config boundary.
- The command **decides for itself whether it is inside the send window** (§4), so the cron
  line stays trivial and the policy stays in the database where operators can see it.
- `symfony/console` is already a dependency; the entry point is a console command so
  `--dry-run` and `--organization` are first-class.

**Rejected:** a resident worker (cannot run on the host) and web-request-driven scheduling
(silently stops sending on quiet days, and is invisible in the run log).

## 4. Selection, window and rate (hub ruling D3)

One run does:

1. Resolve the organizations with scheduled dunning **enabled** (§6).
2. For each, if **now is inside the send window**, ask the upstream for overdue invoices,
   ordered oldest-due first.
3. For each candidate, compute the stage (§5) and call `SendDunningUseCase::execute()`.
   The use case re-checks every guard in §2 — the scheduler never bypasses them, and never
   re-implements them.
4. Stop at the per-run cap.

Recommended initial values (per organization, overridable in settings):

| Setting | Initial value | Why |
| --- | --- | --- |
| Send window | **Mon–Fri, 09:00–18:00 JST** | A dunning email at 03:00 on a Sunday reads as harassment even when its wording is polite. |
| Frequency | **once per day** per invoice-eligible sweep | Interval safety already comes from `dunning_min_interval_days`; the daily cap is about operator surprise, not correctness. |
| Per-run cap | **50 notices** | Bounds the blast radius of a misconfiguration and stays inside shared-host SMTP limits. Exceeded candidates are simply picked up by the next run. |

Holidays are **not** modelled in this iteration (Japanese public holidays would need a
calendar source). Recorded here so the omission is a decision, not an oversight.

## 5. Stage selection (hub ruling D2 — thresholds are per-organization settings with defaults)

`DunningStage` is `initial` / `reminder` / `final` and is chosen per send by the operator
today. The scheduler derives it from **days past due**:

| Days past `due_at` | Stage |
| --- | --- |
| ≥ 3 | `initial` |
| ≥ 14 | `reminder` |
| ≥ 30 | `final` |

Thresholds are per-organization settings carrying these defaults.

🔴 **`final` is not sent automatically — pending owner decision.** The design assumes a
**human gate**: when a candidate reaches the `final` threshold, the scheduler does **not**
send. It surfaces the invoice for approval and an operator sends it with the existing manual
action. Rationale: a final demand is the last message before the relationship changes, and it
is exactly the message an operator would want to reconsider case by case. The owner may decide
that `final` is safe to automate; relaxing this later is a strictly smaller change than adding
the gate afterwards (hub: write it gated, loosen on owner GO).

Escalation never skips a stage: a stage is only reachable once the previous one has actually
been sent for that invoice.

## 6. Opt-in and settings (hub ruling D6 — default OFF)

- Scheduled dunning is **off** for every organization until explicitly enabled. Existing
  deployments must not change behaviour when this ships.
- Settings live on `clear_settings` next to `dunning_min_interval_days` (same tenant scoping,
  same audit event `clear_settings_updated`, no new surface to secure).
- **Every new identifier is registered in [`terminology.md`](../explanation/terminology.md)
  before it appears in code** — settings columns, the CLI command name, any new Problem
  Details slug, and the `metadata` keys of §7. This is binding (CLAUDE.md), and it is the step
  most likely to be skipped under time pressure.

## 7. Audit actor for an unattended send (hub ruling D5)

A scheduled send records **`actor_id = 0`** — this repo's existing "no human actor" value —
and is identified by **`metadata.trigger = 'scheduled'`**, alongside a run id and the
candidate/sent counts.

This reuses a contract that is already written down and already rendered:

- `docs/openapi/openapi.yaml` states it explicitly: *"Actor user id. The implementation always
  writes an integer; an unauthenticated event (e.g. failed login) records 0, not null."*
- `src/Auth/LoginUseCase.php` writes `actorId: 0` for a failed login.
- `frontend/src/pages/audit/AuditLogPage.tsx` renders `actor_id === 0` as `audit.actor.system`
  ("システム / 不明" / "System / unknown").
- `audit_events.actor_id` is `NOT NULL` here (the NENE2 `AuditEvent` type allows null — the
  constraint is Clear's own, and it is deliberate).

Consequently: **no migration, no OpenAPI change, no frontend change.** `metadata.trigger`
separates "unauthenticated event" from "scheduled run" inside that shared value, and `0` is not
a row in `users`, so nothing impersonates a person.

The alternative — making `actor_id` nullable — was ruled out because audit rows are
**immutable**: the existing `actor_id = 0` rows could never be normalised, so "no human actor"
would be expressed two ways permanently and every audit query would have to spell both.

An earlier idea, recording the administrator who *enabled* the schedule as the actor, was
withdrawn: it names someone who did not perform the action, which is worse than a system value.

## 8. Concurrency and idempotency (hub ruling D4)

Two runs must never send the same notice twice. `dunning_min_interval_days` is **not**
sufficient: it prevents a *re-send days later*, not two processes racing within the same
second.

- Mutual exclusion is held in the **database** (advisory/named lock), not a pid or lock file:
  on shared hosting the filesystem is not a reliable arbiter, and the DB is the one component
  both runs certainly share.
- The lock is per organization, so a slow tenant cannot block the others.
- A run that cannot take the lock exits **0** with a logged "already running" — an overlapping
  cron tick is normal operation, not an error.

## 9. Failure handling and observability (hub ruling D7)

- **`--dry-run` is mandatory and is the acceptance instrument**: it prints exactly what would
  be sent (organization, invoice, stage, recipient, reason for any skip) and sends nothing.
- Per-candidate failures do not abort the run; each is logged and the run continues.
- No automatic retry queue in this iteration. A failed send leaves the recorded "attempted"
  trail (§2 #4) and the next run re-evaluates the candidate under the same guards.
- The run log is a file sink under `var/` — the precedent is #331 (readable over SSH on shared
  hosting, where stderr goes nowhere useful).

## 10. Acceptance

1. `--dry-run` output reviewed against a seeded fixture, showing at least: a sent candidate, a
   paused invoice skipped, a too-frequent invoice skipped, a paid invoice skipped, and a
   `final`-threshold invoice **held for approval**.
2. Unit tests with a frozen `ClockInterface` covering the window boundaries, the stage
   thresholds, the per-run cap, and the `final` gate.
3. A concurrency test proving the second run takes no lock and sends nothing.
4. Enabling the feature is visible in the audit log (`clear_settings_updated`), and a scheduled
   send is distinguishable from a manual one (§7).
5. `composer check` and `npm run check` green; `composer spec-parity` green if any enum,
   required field, or `operationId` moved.

## 11. Implementation order

1. Terminology registry entries (binding — before any code).
2. Migration (settings columns) — mysql / pgsql / sqlite.
3. Console command + selection service + lock, behind the default-off switch.
4. Tests (§10), then the settings UI (including its i18n keys).
5. **Ops runbook** — the one page the owner reads. Installing the cron entry in the hosting
   control panel, running `--dry-run` once as the first acceptance step before anything is
   enabled, how to tell a run happened (the `var/` log), and **how to stop it** (turn the
   organization switch off; removing the cron line is the blunt fallback). §3 already says the
   crontab is the owner's seam — this is the page that seam points at.
