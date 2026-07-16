# OpenAPI spec ↔ implementation drift — go/no-go material for #317 (2026-07-16)

[#317](https://github.com/hideyukiMORI/nene-clear/issues/317) is a **stop-gate**:
its Phase 1 edits the *binding* OpenAPI spec and terminology registry, so it
waits for an explicit go/no-go. This document is the material for that decision.
It re-verifies every divergence #317 asserts and reports what the re-verification
found that #317 does not mention.

**Method.** Every claim below was checked against this repository's code on
2026-07-16 at commit `4ebf373` (file:line given). Nothing is carried over
unverified from #317 — several of its claims are **refuted** below. Where a
number comes from a tool, the command is given so it can be re-run. This is a
read-only analysis; it changes no product code and closes no issue.

## Bottom line

1. **#317's central framing is wrong.** It presents Bucket C as a *nullability
   convention debate*. It is not: `nullable: true` still generates `| null`
   (§3.1, verified), and the real driver of nearly every mismatch is a **missing
   `required` entry**. Phase 1 is therefore mostly a *mechanical* spec
   correction, not a design argument.
2. **#317's divergence list is not accurate enough to implement from as written.**
   Two Bucket A claims are wrong, one of which would **introduce** a spec bug if
   applied literally. Bucket B is already done but still listed as pending.
3. **The real gap is larger than #317 describes.** The spec omits **7 shipped
   endpoints**, including the entire MFA/TOTP surface (§2).
4. **Three latent null-safety bugs** were found where the spec and frontend
   assert non-null but PHP demonstrably emits `null` (§3.3). These are worth
   fixing whether or not codegen ever happens.
5. **Enums are in better shape than feared.** Exactly one value-level
   contradiction existed (`client_credit.status`, fixed in
   [#340](https://github.com/hideyukiMORI/nene-clear/pull/340)). All 22 audit
   actions agree three ways. The rest are *absences*, not contradictions (§4).
6. **Nothing in `composer check` compares an enum's members between any two
   artifacts** (§5). The binding registry is enforced by human review alone, and
   there is direct evidence it has already missed cases.
7. **The Phase 1 estimate (~0.5 day) is low** — not because the known work is
   harder, but because the spec omits more than the issue knows about (§7).

## 1. Bucket A — "spec is missing fields the API returns"

| Field claimed missing | Verdict | Evidence |
| --- | --- | --- |
| `User.fiscal_year_end_month` | 🔴 **REFUTED — already in the spec** | Documented at `openapi.yaml:141-145` as an `allOf` overlay on the `/admin/auth/me` 200 (`:137-139`), not as a `User` property. Emitted only by `src/Auth/GetCurrentUserHandler.php:45`. |
| `BankAccount.csv_date_column` | ✅ CONFIRMED | `src/ClearSettings/ClearSettingsResponse.php:29`; absent from `openapi.yaml:1958-1978`; read by `frontend/src/pages/settings/SettingsPage.tsx:150`. |
| `BankAccount.csv_amount_column` | ✅ CONFIRMED | `ClearSettingsResponse.php:30`; `SettingsPage.tsx:151`. |
| `BankAccount.csv_counterparty_column` | ✅ CONFIRMED | `ClearSettingsResponse.php:31`; `SettingsPage.tsx:152`. |
| `BankAccount.csv_header_rows` | ✅ CONFIRMED | `ClearSettingsResponse.php:32`; `SettingsPage.tsx:153`. |
| `ClientCredit.created_at` | ✅ CONFIRMED | `src/Reconciliation/ClientCreditResponse.php:23`; absent from `openapi.yaml:2356-2379`; rendered by `frontend/src/pages/client-credits/ClientCreditsPage.tsx:194`. |
| `ClientCredit.reconciliation_id` | 🟡 PARTIAL | Really returned (`ClientCreditResponse.php:22`), really absent from the spec — but **no frontend consumer**. Declared at `frontend/src/types/index.ts:69`, read nowhere. Lower priority than #317 implies. |
| `ClientCredit.created_by` | 🔴 **REFUTED — never on the wire** | `ClientCreditResponse.php:14-24` does not emit it (it exists only in CSV export, `ExportClientCreditsCsvHandler.php:56`). `frontend/src/types/index.ts:70` declares a **phantom field**. |

### Why the two refutations matter

**`fiscal_year_end_month` must not be added to the `User` schema.** The builder
for `/admin/users` list/detail (`src/User/UserResponse.php:17-23`) deliberately
does not emit it — only `/me` does. Adding it to the shared `User` schema would
make the spec lie about every other User-returning endpoint. The existing `allOf`
design is correct. **Applying #317's Bucket A literally would introduce the very
class of bug the issue exists to remove.**

**`created_by` is a frontend type bug, not a spec bug.** The fix is to delete
`frontend/src/types/index.ts:70` — not to codify a phantom in the spec.

### Bucket B is already done

#317 still lists `ClientCredit.status` as pending work. It is not:
`openapi.yaml:2351-2355` already reads `enum: [open, voided]` and
`docs/explanation/terminology.md:88` already reads `open`, `voided` (landed in
#319). The one remaining artifact, `docs/mcp/tools.json:153`, is fixed by
[#340](https://github.com/hideyukiMORI/nene-clear/pull/340). This item can be
struck from Phase 1.

### Same-class gaps #317 misses

- 🔴 **`ClientCredit.client_id` nullability** — see §3.3, bug ①.
- 🟡 **The `BankAccount` fix is not GET-only.** `UpdateClearSettingsRequest`
  `$ref`s the same incomplete schema (`openapi.yaml:2021-2024`) and the PUT
  handler reads all four `csv_*` fields
  (`src/ClearSettings/UpdateClearSettingsHandler.php:75-78`). Both sides.
- Also absent from the spec but really emitted: `Reconciliation.reason_code`
  and `.confirmed_by` (`src/Reconciliation/ReconciliationResponse.php:20-21`),
  `BankImportBatch.reversed_at` (`BankImportBatchResponse.php:23`).
- Also **phantom** (declared in `types/index.ts`, never emitted):
  `BankImportBatch.imported_by` (`:22`), `ClientCredit.created_by` (`:70`).
- Also **missing from the frontend type** though present in spec + response:
  `ReconciliationAllocation.source` / `.manual_receivable_id`,
  `UpstreamInvoice.overdue`.
- `UpstreamClient` (`types/index.ts:209-213`) has **no corresponding schema
  anywhere in the spec**.

## 2. Undocumented endpoints — 7 shipped routes absent from the spec

Not mentioned in #317. Reproduce by diffing router registrations against the
spec's `paths`:

| Method + path | Registered at | In spec? |
| --- | --- | --- |
| `POST /admin/auth/totp/setup` | `src/Mfa/MfaRouteRegistrar.php:33` | ❌ |
| `POST /admin/auth/totp/enable` | `src/Mfa/MfaRouteRegistrar.php:34` | ❌ |
| `GET /admin/auth/totp` | `src/Mfa/MfaRouteRegistrar.php:35` | ❌ |
| `DELETE /admin/auth/totp` | `src/Mfa/MfaRouteRegistrar.php:36` | ❌ |
| `POST /admin/auth/login/mfa` | `src/Auth/VerifyMfaLoginHandler.php` | ❌ |
| `GET /admin/dunning-notices/preview` | `src/Dunning/DunningRouteRegistrar.php:40` | ❌ |
| `POST /admin/dunning-notices/test-send` | `src/Dunning/DunningRouteRegistrar.php:37` | ❌ |

`grep -ci 'totp\|/login/mfa' docs/openapi/openapi.yaml` → **0**. The **entire MFA
surface is undocumented** — a security-relevant surface shipped under #293/#216.

Two of these (`previewDunningNotice`, `sendTestDunningNotice`) are *registered in
the binding terminology registry* (`terminology.md:297`) while having no
operation in the spec at all.

> **Accuracy note.** The same diff reports `GET /health` as "in the spec, not in
> the router". That is a **false positive** of the method: `/health` is
> registered by NENE2's `RuntimeApplicationFactory`, not by a `$router->get(...)`
> call in `src/`. It is not a gap, and no other reverse-direction gap exists.

## 3. Bucket C — representation

### 3.1 The framing is wrong: this is `required`, not `nullable`

#317 asks the owner to "decide: mark nullable fields `nullable: true` in the
spec (and/or configure generation) so generated types express `| null`, vs.
aligning the code." **That decision does not need making — `nullable: true`
already generates `| null`.** Verified against the spec's own output:

```
openapi.yaml:1872-1876   User.organization_id — required + nullable: true
schema.gen.ts:813        organization_id: number | null;      ← the null survives
openapi.yaml:2437-2441   ManualReceivable.due_at — non-required + nullable: true
schema.gen.ts:1142       due_at?: string | null;              ← both survive
```

The generation rules are therefore simply:

| spec | generated |
| --- | --- |
| `required` + plain | `f: T` |
| `required` + `nullable: true` | `f: T \| null` |
| non-required + plain | `f?: T` |
| non-required + `nullable: true` | `f?: T \| null` |

So every `?` in a generated type traces to **absence from a `required` list**,
not to a nullability convention. The mismatches are overwhelmingly the spec
failing to declare fields required that the response builders **always** emit —
e.g. `src/Receivable/ManualReceivableResponse.php:19-34` emits all 14 keys
unconditionally. **The code is right; the spec's `required` lists are wrong.**
That is a mechanical fix, not a convention debate.

> **One real decision hides here.** `nullable: true` was *removed* in OpenAPI
> 3.1 / JSON Schema 2020-12 (the 3.1 form is `type: [string, 'null']`), yet this
> spec declares `openapi: 3.1.0` and uses `nullable: true` **24 times**. It works
> today only because `openapi-typescript@7` tolerates it. The sibling
> `nene-invoice` — which already runs a codegen drift gate — uses the 3.1 form
> **116×** and `nullable: true` once. So the question is not "does nullable
> work" but "**do we migrate to the 3.1-conformant form to match invoice**",
> which is a consistency call, not a blocker.

### 3.2 Measured mismatches

**The "23 type errors" figure cannot be reproduced.** It came from an experiment
(re-point `types/index.ts` + fix call sites) that was never committed, so its
count includes call-site fallout that no longer exists to measure. Two
independent measurements were taken instead:

- **20** — schema-level assignability errors across the 14 overlapping type
  pairs, measured by generating types and asserting mutual assignability, then
  running `npm run type-check`.
- **28** — individual field-level mismatches, by comparing every schema property
  against its hand-written counterpart: **11** where the spec is non-required but
  the code says `| null`, **17** other (mostly non-required vs required), plus
  **4** fields where spec and code already agree.

These count different things and neither is "the number of things to fix". Both
are reported so the owner can see the shape, not to pick a headline number.

Reproduce the first with:

```sh
npx -y openapi-typescript@7 docs/openapi/openapi.yaml -o /tmp/schema.gen.ts
# assign each components['schemas'][X] to its counterpart in
# frontend/src/types/index.ts, both directions, then: npm run type-check
```

### 3.3 🔴 Three latent null-safety bugs (not representation drift)

These are cases where the spec and/or the frontend assert non-null but PHP
demonstrably emits `null`. They are real defects independent of #317:

| # | Field | Spec | Frontend | PHP truth |
| --- | --- | --- | --- | --- |
| ① | `ClientCredit.client_id` | `:2358` **required, non-null** | `:64` `number` | `src/Reconciliation/ClientCredit.php:13-14` `?int` — *"null for `manual`"*. Column made nullable on purpose (`database/migrations/20260608140000_…:24-26`). **A strict client would reject real manual-credit responses.** UI half filed as [#341](https://github.com/hideyukiMORI/nene-clear/issues/341). |
| ② | `DunningNotice.due_at` | `:2568-2570` **required, non-null** | `:110` `string \| null` | `src/Dunning/DunningNotice.php:20-21` `?string` — *"null when the invoice had no due date"*. Spec is wrong; frontend is right. |
| ③ | `ReconciliationAllocation.invoice_id` | `:2267-2271` non-req + nullable | `:42` `number` | `src/Reconciliation/ReconciliationAllocation.php:14-15` `?int` — *"null for `manual`"*. **Frontend is wrong**; spec is right. |

### 3.4 🔴 A spec bug: a `required` entry naming a property that does not exist

`DunningNotice.required` (`openapi.yaml:2545-2546`) lists **`outstanding_cents`**
— which is not a declared property. The real property is
`outstanding_at_send_cents` (`:2565`). The required entry is dead, and the real
field is silently optional.

```
required にあるが property に無い: ['outstanding_cents']
property にあるが required に無い: ['outstanding_at_send_cents']
```

A sweep of all 55 schemas found **this is the only occurrence** — the rest are
structurally sound. Note that `composer openapi` passes anyway: it checks that
`$ref`s resolve, not that `required` names real properties.

### 3.5 `AuditEvent.action` — no reconciliation work needed

The spec says `string` (`:2609-2611`); the frontend has a 22-member `AuditAction`
union (`types/index.ts:158-180`) driving an exhaustive `Record`
(`AuditLogPage.tsx:15-38`). The union is **exactly set-equal** to the 22 values
the backend emits (one emit site each, e.g. `ImportBankCsvUseCase.php:84`,
`EnableTotpUseCase.php:59`) **and** to the registry (`terminology.md:111-133`).
Registry, union, and emit set are three-way identical; the framework contributes
no actions of its own.

So this is a **pure yes/no**, with no cleanup attached: register the 22-member
enum in the spec (faithful — the registry already binds it, and the generated
union would drive the exhaustive Record for free), **or** keep `type: string` and
hold `AuditAction` as a frontend-only display union.

> One argument for keeping `string`: `audit_events` is **append-only historical
> data**, and the read filter accepts arbitrary strings
> (`ListAuditEventsHandler.php:33`). Registering the enum makes the *response*
> type narrower than the column, so a legacy row carrying a retired action would
> violate the generated type. Mitigating: the Record is not load-bearing at
> runtime — `AuditLogPage.tsx:170` passes `fallback={event.action}`, so an
> unknown action degrades to the raw string rather than crashing.

### 3.6 `ProblemDetails.errors` — the spec is already correct

#317 frames this as "the spec's shape differs from the hand-written type". The
re-verification inverts that: **the spec is right and the hand-written type is
the outlier.**

`errors` is an RFC 9457 *extension member* — NENE2 passes it as one
(`vendor/hideyukimori/nene2/src/Error/ErrorHandlerMiddleware.php:67-77`), which
is exactly why the spec models base `ProblemDetails` with
`additionalProperties: true` (`:1772-1794`) plus a separate
`ValidationProblemDetails` variant (`:1795-1805`). The field set matches exactly
(`field`/`message`/`code`, all required, all `string` —
`vendor/hideyukimori/nene2/src/Validation/ValidationError.php:37-44`). Only the
*decomposition* differs: the hand-written type flattens both into one interface
with optional `errors` (`types/index.ts:140-146`).

The practical consequence: `components['schemas']['ProblemDetails']` has no
`errors` member, so re-pointing breaks `client.ts:28` and `:155`. But
`client.ts` **already imports `isValidationProblemDetails` and the
`ValidationProblemDetails` type from `@hideyukimori/nene2-client`**
(`client.ts:1-7`) — the package already ships the correct two-schema model.
**No spec change is needed**; the decision is only whether to type
`ApiError.problem` as `ProblemDetails | ValidationProblemDetails` and let the
existing guard narrow it.

## 4. Enum sweep — one contradiction, four absences

Every enumerated value set was compared across five artifacts (PHP enums,
`openapi.yaml`, `tools.json`, `frontend/src/types/index.ts`, and the binding
registry).

**The good news, stated plainly:** every enum Clear owns and enumerates in more
than one artifact — bank transaction status, batch status, reconciliation
status, manual receivable status, receivable source, user role, user status,
capability, currency, `sort_dir`, and all **22 audit actions** — **agrees
exactly everywhere it appears**. `client_credit.status` was the *only*
value-level contradiction, and #340 fixes it.

The remaining findings are a different kind of problem:

| # | Finding | Evidence |
| --- | --- | --- |
| D1 | 🔴 **`dunning_notice.status` is a phantom registry entry.** The binding registry registers `sent`, `failed` — but there is no PHP enum, no `status` column, no schema property, no frontend field. The registry advertises a status set the product does not have. | `terminology.md:91`; no `status` column in `database/migrations/20260531200100_create_dunning_notices_table.php`; no `DunningNoticeStatus` in `src/` |
| D2 | 🔴 **Dunning `stage` (`initial\|reminder\|final`) is absent from the spec.** Live in PHP, registered, sent by the frontend — but `sendDunningNotice`'s requestBody (`:1303-1315`) declares only `invoice_id`. The API accepts a parameter the contract does not document. | `src/Dunning/DunningStage.php:16-18`; `terminology.md:190`; `frontend/src/api/endpoints.ts:401` |
| D3 | 🔴 Two registered operationIds have **no operation in the spec** — see §2. | `terminology.md:297` vs 0 spec hits |
| D4 | 🟡 **`account_type` values are unregistered.** `ordinary\|current` ships in PHP, spec, and frontend — all agreeing — but the registry registers only the field name (`terminology.md:182`), never the values. Per registry rule 2 ("register before you use"), a live rule violation. Same class, lower stakes: health `ok\|degraded` / `ok\|error`. | `src/BankImport/AccountType.php:9-10`; `openapi.yaml:1957` |
| D5 | 🟢 **Semantic tension (defensible).** The registry models `overdue` as a computed flag, not a status — but PHP passes `'overdue'` as a *status* filter value upstream. Defensible (Clear does not own Invoice's vocabulary); worth an explicit note in the registry. | `src/InvoiceUpstream/ListUpstreamInvoicesHandler.php:30` vs `terminology.md:96` |

## 5. Why CI did not catch any of this

`composer check` runs `test, analyse, cs, openapi, mcp, conformance`
(`composer.json:49-56`). **None of them compares an enum's members between two
artifacts.**

- **`composer mcp`** (`vendor/hideyukimori/nene2/tools/validate-mcp-tools.php`)
  validates five things: tool `name` non-empty; `safety` in a known set; `source`
  resolves to a real operation; the declared `operationId` matches the one at
  that path+method; `responseSchemaRef` equals the 200 `$ref`. **`inputSchema` is
  read from JSON and never inspected** — the string `inputSchema` does not appear
  anywhere in the validator. Every enum, `required`, and property type inside it
  is unchecked. `tools.json` could declare `"status": {"enum": ["banana"]}` and
  the gate would still print *"MCP tool catalog is valid."*
- **`composer openapi`** (`tools/validate-openapi.php`) checks top-level keys,
  `openapi: 3.1.0`, and that internal `$ref`s resolve. It never opens
  `tools.json`, never loads PHP, never looks at `enum` — and, per §3.4, never
  checks that a `required` entry names a real property.
- **`tests/OpenApi/OpenApiContractTest.php`** has a single test method
  (`test_openapi_document_parses_and_documents_get_health`) with no enum
  assertions.

**All four parity axes — PHP↔spec, PHP↔frontend, spec↔frontend, and
anything↔registry — are entirely unguarded.** The binding registry is enforced by
human review only. D1 and D3 are direct evidence that human review has already
missed cases; #264 is evidence that a miss survived a week *after being
explicitly enumerated in the issue's own comments*.

**Recommendation for #317's drift gate:** enum-member parity and `required`
sanity must be in scope, not just type generation. Those are the checks that
would have caught #264, D1, D2, and §3.4 mechanically.

## 6. Tooling note — verified

#317 says `openapi-typescript@7` declares a `typescript@^5` peer while Clear is
on `typescript@~6`, so `npm i -D` fails ERESOLVE, and proposes either a pinned
`npx` or an override. Both halves were tested:

- **ERESOLVE confirmed.** `npm i -D openapi-typescript@7 --dry-run` →
  `npm error code ERESOLVE … Found: typescript@6.0.3 … dev typescript@"~6.0.2"`.
- **The pinned-`npx` workaround works.** `npx -y openapi-typescript@7
  docs/openapi/openapi.yaml -o …` → **7.13.0** generated **2849 lines in 127 ms**
  without touching `package.json` or `node_modules`, and correctly emitted
  `ClientCreditStatus: "open" | "voided"`.

So Phase 2's tooling question has an empirically verified answer: **pinned `npx`
in the `codegen` script; no override needed; `npm ci` stays clean.** Note
`nene-invoice` pins `openapi-typescript` at `7.13.0` and gates it with a
`codegen:check` script — the same version this test used.

## 7. Effect on the estimate

#317 estimates Phase 1 at ~0.5 day, "mostly verification". Two forces pull that
in opposite directions:

**Cheaper than estimated:**
- Bucket B is already done (§1) — strike it.
- The nullability "convention decision" does not exist (§3.1) — it is a
  mechanical `required`-list correction.
- `AuditEvent.action` needs no reconciliation, only a yes/no (§3.5).
- `ProblemDetails` needs no spec change at all (§3.6).

**More expensive than estimated:**
- **7 undocumented endpoints (§2)**, including the whole MFA surface. These need
  request/response schemas written from scratch — not a field added to an
  existing schema. This is the bulk of the real work and #317 does not know
  about it.
- 3 latent null-safety bugs (§3.3) needing a canonical ruling (PHP or frontend?).
- D1/D2/D4 (§4) — registry corrections, each touching a **binding** document.
- 2 of 8 Bucket A items are wrong (§1) — the list must be re-derived, not applied.

**Net:** Phase 1 *as scoped in #317* is probably still ~0.5 day and is now
better understood. Phase 1 *as the word "binding" requires* — the spec actually
matching the implementation — is materially larger, and the MFA surface
dominates it. Whether those 7 endpoints belong in Phase 1 or a separate issue is
a scoping decision for the owner. Documenting them is not optional if the spec
is to be called binding.

## Notes on accuracy

- Everything above is measured at `4ebf373` on 2026-07-16. Where a method has a
  known false positive it is called out inline (§2, `/health`).
- The 20 (§3.2) and the 23 (#317) **measure different things** and neither
  refutes the other. #317's 23 counted a re-point plus call-site fallout; the 20
  counts schema-level assignability only.
- `client_credit.status` is excluded from §4's contradiction count because #340
  fixes it; at `4ebf373` it is still present in `docs/mcp/tools.json:153`.
- §3.1's claim that `nullable: true` survives generation is specific to
  `openapi-typescript@7.13.0`. It is a *tolerance*, not conformance — the
  keyword is not valid 3.1.
- This document proposes no code changes and closes no issue. #317 remains a
  stop-gate awaiting go/no-go.
