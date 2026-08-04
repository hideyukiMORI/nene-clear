# Scheduled dunning — operations runbook

How to turn unattended dunning on, check that it is working, and turn it off.

Scheduled dunning is **off for every organization** until someone enables it. Deploying
this feature changes nothing on its own — neither installing the cron entry nor
upgrading the app starts any sending. Two separate things must both be true before a
single notice goes out:

1. the cron entry exists, **and**
2. that organization's schedule is switched on.

Either one alone sends nothing. That is deliberate: it means you can install the cron
line early and still control the blast radius from the application.

> **Read this before enabling anything: [step 2](#2-look-before-you-send-dry-run).**
> The dry run is the acceptance step, not a formality. It is the only way to see what
> the first real run would do while it still cannot do it.

---

## 1. Install the cron entry

One-shot command, run on a schedule. There is no resident worker — shared hosting
cannot keep one alive.

```
cd /path/to/app && php8.4 tools/send-scheduled-dunning.php >> var/log/dunning-cron.log 2>&1
```

Schedule it **every 15 minutes**. The command decides for itself whether it is inside
the send window, so a tick outside working hours does nothing and costs almost nothing.

Notes for this deployment:

- **`php8.4`** is the binary name on HETEML. `php` may be an older version — check with
  `php8.4 -v` over SSH before saving the entry. Getting this wrong fails silently in
  the sense that nobody is watching the log yet.
- `/path/to/app` is the directory that **contains** `tools/` and `vendor/` — one level
  above the docroot in this deployment, not `public_html/`.
- The redirect matters. On shared hosting stderr goes nowhere useful, so without
  `>> var/log/dunning-cron.log 2>&1` a failing run leaves no trace at all.
- `var/` must be writable by the cron user. It already is if the app runs.
- Precedent: `tools/sweep-demo.php` runs from cron on the same host the same way
  (`docs/demo.md` §6).

**Installing this entry is safe on its own.** With every organization's schedule off,
each tick exits immediately having sent nothing.

---

## 2. Look before you send (dry run)

Run this once over SSH **before** enabling any organization:

```
cd /path/to/app && php8.4 tools/send-scheduled-dunning.php --dry-run
```

It prints exactly what a real run would do and **sends nothing**. It also takes no
lock, so it can never suppress the scheduled run behind it — you can run it any time,
including while cron is active.

Add `--organization=<id>` to look at one organization only.

### Reading the output

```
[2026-08-04 14:28:13+09:00] dunning run 73d21c04 (DRY-RUN): 3 candidate(s), 1 sent (would send)
  org 6: skipped (window_closed)
  org 7  INV-0042      sent              initial   12 day(s) past due
  org 7  INV-0038      awaiting_approval final     41 day(s) past due
  org 7  INV-0051      too_frequent      initial   ...
```

Every invoice the run considered gets a line, **including the ones it decided against**.
That is the point: if an invoice you expected to see is missing, the run never
considered it — as opposed to considering it and passing.

| Outcome | Meaning |
| --- | --- |
| `sent` | Sent (or, in a dry run, would be sent) |
| `below_threshold` | Not far enough past due yet |
| `no_due_date` | The invoice has no due date, so "days past due" is undefined. Dun it by hand if you need to |
| `awaiting_approval` | Reached the `final` threshold. **Never sent unattended** — send it by hand after deciding |
| `paused` | Someone paused dunning for this invoice |
| `too_frequent` | Inside the minimum interval since the last notice |
| `already_paid` | Settled, or nothing outstanding |
| `cap_reached` | The per-run cap was hit; the next run picks it up |
| `failed` | The send threw. The run continued; check the message |

And for whole organizations that were passed over:

| Skip reason | Meaning |
| --- | --- |
| `window_closed` | Outside the send window right now. **Expected** most of the day |
| `already_running` | A previous tick is still working. **Normal**, not an error |
| `not_enabled` | The schedule is off for that organization |
| `failed: …` | Something broke for that organization — most likely the Invoice API being unreachable. **Other organizations still ran** |

**A clean dry run is the acceptance criterion.** Look at the `sent` lines and ask
whether you would be comfortable if those emails went out in the next 15 minutes,
because that is what enabling does.

---

## 3. Turn it on for an organization

> **There is no settings screen for this yet.** The editing UI arrives with the
> settings rework (#409, F4). Until then, enable it through the API or the database.

Via the API (as an admin), remembering that
[`PUT /admin/clear-settings` is a full replace](clear-settings-full-replace.md) — read
the settings first, change the one field, send everything back:

```
GET  /admin/clear-settings          # take the whole object
PUT  /admin/clear-settings          # send it back with is_dunning_schedule_enabled: true
```

Sending only `{"is_dunning_schedule_enabled": true}` will reset every other setting to
its default. This is the single most likely mistake on this page.

### The settings

| Setting | Default | What it does |
| --- | --- | --- |
| `is_dunning_schedule_enabled` | `false` | The master switch for this organization |
| `dunning_initial_after_days` | `3` | Days past due before the first notice |
| `dunning_reminder_after_days` | `14` | Days past due before the second |
| `dunning_final_after_days` | `30` | Days past due before `final` — **which is never sent automatically** |
| `dunning_window_start_hour` | `9` | Send window opens (inclusive) |
| `dunning_window_end_hour` | `18` | Send window closes (exclusive) |
| `is_dunning_weekdays_only` | `true` | Skip Saturdays and Sundays |
| `dunning_max_per_run` | `50` | Most notices one run may send |

Two settings are rejected rather than accepted-and-ignored: a window whose start is not
before its end, and thresholds that do not ascend. Both would make dunning stop or
misbehave in ways that look like nothing happening.

**Enabling is recorded in the audit log** as `clear_settings_updated`, with the whole
schedule in the `after` snapshot. You can always answer "who turned this on, and when".

### What the escalation actually does

Stages go `initial` → `reminder` → `final`, and **a stage is only reachable once the
previous one has actually been sent**. An invoice that has sat untouched for 60 days
does not get a final demand out of nowhere — it gets `initial` on the next run,
`reminder` after the minimum interval, and then waits for a human.

`final` is never sent unattended. It appears as `awaiting_approval` and an operator
sends it with the existing button.

---

## 4. Tell whether it is running

```
tail -n 50 /path/to/app/var/log/dunning-cron.log
```

Every run writes one summary line plus a line per candidate, so a working installation
produces output roughly every 15 minutes even when it sends nothing:

```
[…] dunning run 8f3a… (LIVE): 0 candidate(s), 0 sent
  org 7: skipped (window_closed)
```

**An empty log is the thing to worry about.** It means the cron entry never fired —
wrong path, wrong PHP binary, or `var/` not writable. It does *not* mean "nothing was
due"; a run that has nothing to do still says so.

To find everything one run sent, search the audit log for its run id: scheduled sends
record `trigger: scheduled` and `dunning_run_id` in the `dunning_sent` event's
metadata. They also record actor `0` — "no human actor" — which is why the audit screen
shows them as *System*.

Exit codes, if you wire monitoring to them:

| Code | Meaning |
| --- | --- |
| `0` | The run completed. Includes "nothing to do" and "already running" |
| `1` | The run could not start — usually the database or `NENE_INVOICE_API_BASE_URL` is not configured |
| `2` | The run completed but at least one candidate failed to send |

---

## 5. Stop it

**To stop one organization** — turn its switch off:

```
PUT /admin/clear-settings   with is_dunning_schedule_enabled: false
```

(the whole object, per [full replace](clear-settings-full-replace.md)). This is the
normal control and it takes effect on the next tick.

**To stop everything** — remove the cron entry. This is the blunt fallback: it stops
all organizations at once and needs no application access. Use it if you cannot log in,
or if you want sending stopped *now* and will sort out which organization later.

Neither one recalls anything already sent. Email does not work that way, and that is
the reason `final` is gated on a human in the first place.

**Pausing a single invoice** is a different, finer control that already exists and is
unrelated to the schedule: pause it, and every path — scheduled or manual — skips it.

---

## 6. When something looks wrong

| Symptom | Most likely cause |
| --- | --- |
| Log file empty or missing | The cron entry never fired: wrong path, `php` instead of `php8.4`, or `var/` not writable |
| `org N: skipped (window_closed)` all day | The window or weekday setting is narrower than you think. Check both, and remember the end hour is exclusive |
| `refusing to run …` and exit 1 | `NENE_INVOICE_API_BASE_URL` is not set. The command refuses rather than sweep against an empty stand-in, so a misconfiguration cannot look like a quiet day |
| `org N: skipped (failed: … unreachable)` | The Invoice API is down or the URL is wrong. Other organizations still ran |
| Invoices stuck at `awaiting_approval` | Working as designed. `final` needs a person |
| Scheduled dunning turned itself off | Almost certainly a partial `PUT` — see [full replace](clear-settings-full-replace.md). The audit log will show the `clear_settings_updated` event that did it |

---

## Related

- [`dunning-scheduler-design.md`](dunning-scheduler-design.md) — why it is built this way
- [`clear-settings-full-replace.md`](clear-settings-full-replace.md) — the settings API pitfall
- Issue #400 — the design gate and its implementation trail
