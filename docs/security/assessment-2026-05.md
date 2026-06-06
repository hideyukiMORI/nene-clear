# Security Assessment — NeNe Clear (2026-05)

**Scope:** authorized black/grey-box assessment of the NeNe Clear API against a
local MySQL-backed instance seeded with a large multi-tenant dataset
(3 organizations, 19 users, 1,500 bank transactions, 90 reconciliations,
45 client credits, 60 dunning notices). Localhost only; no external targets.

**Harness:** `tests/security/seed.php` (dataset) + `tests/security/probe.sh`
(round 1) + `tests/security/probe2.sh` (round 2, deeper vectors). Re-runnable
against any instance via `DB_*` env vars.

**Result:** two rounds, 34 checks pass, **0 remaining exploitable
vulnerabilities**. Four issues found and fixed (incl. one **critical**
privilege escalation found in round 2); all re-verified.

---

## What was tested

| Class | Probes | Outcome |
| --- | --- | --- |
| **Authentication** | missing/garbage token, `alg:none` forgery, stripped/bad signature, payload tamper (role escalation), expired token | All rejected with 401 |
| **Multi-tenant isolation (IDOR)** | cross-tenant user list, reconciliation read, reconciliation reverse (write), user delete | All scoped/blocked (404/422) — no cross-org leak |
| **RBAC / capability** | viewer→write users, viewer→read orgs, org-admin→create org | All 403 |
| **SQL injection** | filter params (`' OR '1'='1`, `DROP TABLE`, `UNION SELECT`), login fields | Parameterized; table row counts intact |
| **Financial integrity** | negative allocation, integer overflow amount, empty allocations | All 422 (no negative/overflow posting) |
| **Input / method / disclosure** | 1 MB body, malformed JSON, unsupported methods, not-found error body, path traversal in id | Rejected; **no stack traces / paths leaked** |
| **Transport / CORS** | preflight from hostile Origin | No permissive CORS headers (same-origin) |
| **Brute force** | 12 rapid wrong-password logins | Throttled after 5 (429) — see F-2 |

### Round 2 — deeper / creative vectors

| Class | Probes | Outcome |
| --- | --- | --- |
| **Mass assignment** | inject `organization_id`, `role:superadmin`, `id` in create-user body | `organization_id`/`id` ignored (token-derived); `role:superadmin` **was** accepted → F-4 (fixed) |
| **Privilege escalation** | org-admin promote member→superadmin (create + update) | Blocked after fix (403) |
| **IDOR numeric/type** | id = 0, -1, huge, float, `1e3`, `abc`, injection, `null` | All 404/400/422 |
| **Idempotency / double-spend** | same `Idempotency-Key` twice | No duplicate (replay returns the original) |
| **JSON/type confusion** | arrays/objects for scalars, 500-level nesting | All 401/422, no crash |
| **Pagination DoS** | limit = 9,999,999 / 0 / negative / non-numeric | All 422 (capped) |
| **Stored/second-order injection** | SQL+XSS in `reversal_reason` | Stored as data; table intact |
| **User enumeration** | unknown-user vs wrong-password response | Identical status+body |
| **JWT/header edge** | lowercase `bearer`, no scheme, mutated token | All 401 |
| **Content-Type confusion** | form-encoded login | 400 |

---

## Findings

### F-1 — Reconciliation confirm endpoint mismatch (fixed) · severity: high (functional/integration)
The frontend posted match confirmations to `POST /admin/reconciliations/confirm`,
but the backend route is `POST /admin/reconciliations`. In production the confirm
action would return **405 Method Not Allowed** — reconciliation could not be
finalized. (Unit/E2E suites passed only because they mocked the wrong path.)
**Fix:** `frontend/src/api/endpoints.ts` now posts to `/admin/reconciliations`;
unit + E2E mocks updated. Surfaced only because the probe hit the real backend.

### F-2 — No login rate limiting / lockout (fixed) · severity: medium
12 consecutive failed logins all returned 401 with no throttling. **Fix:** added
a DB-backed `PdoLoginThrottle` (table `login_attempts`) keyed on email + client
IP. After 5 failures within a 15-minute window the identifier is locked for
15 minutes (HTTP 429 `too-many-login-attempts`, even with the correct password);
a successful login clears the counter. Verified: round-2 probe Q now sees
`401×5 → 429`.

### F-4 — Privilege escalation: org-admin could mint a superadmin (fixed) · severity: critical
Round 2 found that an organization-scoped **admin** could create (or update) a
user with `role: superadmin` via the request body — yielding an account with
platform-wide `manage_organizations` capability (full escalation from one tenant
to cross-tenant control). (`organization_id` was *not* mass-assignable — it is
taken from the caller's token — but `role` was unconstrained.)
**Fix:** invariant in `CreateUserUseCase` / `UpdateUserUseCase` — the
`superadmin` role may only exist with a `null` organization, so an org-scoped
caller assigning it is rejected with 403 `role-not-assignable`. Verified:
org-admin superadmin-create → 403; normal roles still 201.

### F-3 — MySQL migration portability (fixed) · severity: high (deployment)
`CreateClearSettingsTable` declared `organization_id` as a PRIMARY KEY without
`null => false`. SQLite tolerated it; **MySQL rejected the whole migration**
(`1171 All parts of a PRIMARY KEY must be NOT NULL`), so a production (MySQL)
deploy could not migrate past this point. **Fix:** added `'null' => false`.

---

## Confirmed-safe (no action needed)

- **JWT** — HS256 signature enforced; `alg:none`, tampered payloads, and expired
  tokens all rejected. Secret is env-only.
- **Tenant scoping** — every read/write is constrained to the caller's
  `organization_id`; cross-tenant access returns 404, never another org's data.
  (Backed by the SQL-level `organization_id` filters added earlier.)
- **RBAC** — `CapabilityMiddleware` enforces read/write capabilities per role;
  viewers cannot write, org-admins cannot touch the superadmin org surface.
- **SQL injection** — all queries are parameterized (`DatabaseQueryExecutor`);
  no payload altered data or leaked columns.
- **Financial invariants** — negative, overflow, and empty allocations are
  rejected at validation; over-allocation is blocked by the use case.
- **Error hygiene** — Problem Details responses carry no stack traces, file
  paths, or SQL fragments, even with `APP_DEBUG=true`.
- **CORS** — no permissive cross-origin headers; API is same-origin.

---

## Reproduce

```bash
docker compose up -d                       # MySQL on :3383
DB_ADAPTER=mysql DB_HOST=127.0.0.1 DB_PORT=3383 DB_NAME=nene_clear \
  DB_USER=nene_clear DB_PASSWORD=nene_clear composer migrations:migrate -- --no-interaction
DB_HOST=127.0.0.1 DB_PORT=3383 php tests/security/seed.php
php -S localhost:8082 sec-router.php &     # MySQL-backed instance
bash tests/security/probe.sh               # round 1
bash tests/security/probe2.sh              # round 2 (deeper vectors)
```
