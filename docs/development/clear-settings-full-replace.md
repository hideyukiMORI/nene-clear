# `PUT /admin/clear-settings` is a full replace

**A field you leave out of the body is reset to its default. It is not preserved.**

This page exists because that is not what most people assume a `PUT` on a settings
resource does, and because getting it wrong is silent: the request succeeds, the
response is a `200`, and a setting the operator turned on is quietly off again.

## The rule

To change one setting, send **all** of them:

1. `GET /admin/clear-settings`
2. change the field you mean to change
3. `PUT` the whole object back

Sending only the field you are editing will reset every field you omitted.

```jsonc
// ❌ resets the dunning schedule, the fiscal year-end month and the bank accounts
{ "dunning_min_interval_days": 10 }

// ✅ read-modify-write
{
  "upstream_base_url": "https://invoice.example/api",
  "upstream_token_ref": "NENE_INVOICE_BEARER_TOKEN",
  "dunning_min_interval_days": 10,
  "fiscal_year_end_month": 3,
  "is_dunning_schedule_enabled": true,
  "dunning_initial_after_days": 3,
  "dunning_reminder_after_days": 14,
  "dunning_final_after_days": 30,
  "dunning_window_start_hour": 9,
  "dunning_window_end_hour": 18,
  "is_dunning_weekdays_only": true,
  "dunning_max_per_run": 50,
  "bank_accounts": [ /* the complete list */ ]
}
```

> **Calling this from shared hosting?** Send the token in `X-Authorization` as well as
> `Authorization` — HETEML-class hosts strip the standard header, and the resulting
> `401` looks like a bad token rather than a lost one. See the `bearerAuth` description
> in [`../openapi/openapi.yaml`](../openapi/openapi.yaml).

The bank accounts behave the same way and always have: the stored set is replaced
by the array you send, so an omitted `bank_accounts` empties it.

## Why it is built this way

The endpoint backs a settings **screen**, which loads the whole object and saves the
whole object. For that one caller, replace is the simpler and more predictable
contract — there is no merge to reason about and no way for a stale field in the
client to be silently kept.

The cost is that the endpoint is unforgiving to any *other* caller, which is what
this page is for.

## What enforces it

Prose is not enforcement. The behaviour is pinned by tests, so a future change that
quietly turns this into a merge — or a UI that starts sending partial bodies — fails
before it ships:

| Test | What it pins |
| --- | --- |
| `ClearSettingsHttpTest::test_put_is_full_replace_so_an_omitted_field_is_reset_not_preserved` | Enabling scheduled dunning, then PUTting a body without that field, turns it back off |
| `SettingsPage.test.tsx` — *saves the whole settings object* | The settings screen sends every field, including the ones it has no controls for |
| `PdoClearSettingsRepositoryTest::testSaveWritesTheDunningScheduleItIsGiven` | The repository persists exactly the entity it is handed, on both the INSERT and UPDATE branches |

The middle one matters most in practice. The settings screen currently has **no
controls** for the scheduled-dunning fields (they arrive with the A2 settings
rework), so it echoes back the values it loaded. Without that, every unrelated save
— editing a bank account, changing the upstream URL — would turn scheduled dunning
off, with no error and nothing on screen to suggest it happened.

## If this ever becomes a merge

That would be a breaking change for the screen, not just an addition: the screen
relies on replace to delete bank accounts. Removing an account is expressed by
sending the list without it. Under merge semantics that would become a no-op, and
deletion would need its own representation.

## Related

- Issue #284 — where this was first raised as undocumented
- Issue #314 — a settings-save bug that only reproduced on MySQL; the same endpoint
- [`dunning-scheduler-design.md`](dunning-scheduler-design.md) §6 — the scheduled-dunning
  settings this now carries
