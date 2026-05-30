# Security Assessment — NeNe Clear (2026-05)

**Scope:** authorized black/grey-box assessment of the NeNe Clear API against a
local MySQL-backed instance seeded with a large multi-tenant dataset
(3 organizations, 19 users, 1,500 bank transactions, 90 reconciliations,
45 client credits, 60 dunning notices). Localhost only; no external targets.

**Harness:** `tests/security/seed.php` (dataset) + `tests/security/probe.sh`
(attack probes). Re-runnable against any instance via `DB_*` env vars.

**Result:** 20 checks pass, **0 exploitable vulnerabilities**. Three robustness
issues found and fixed; one operational hardening recommendation remains.

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
| **Brute force** | 12 rapid wrong-password logins | All 401 — **no throttle (see F-2)** |

---

## Findings

### F-1 — Reconciliation confirm endpoint mismatch (fixed) · severity: high (functional/integration)
The frontend posted match confirmations to `POST /admin/reconciliations/confirm`,
but the backend route is `POST /admin/reconciliations`. In production the confirm
action would return **405 Method Not Allowed** — reconciliation could not be
finalized. (Unit/E2E suites passed only because they mocked the wrong path.)
**Fix:** `frontend/src/api/endpoints.ts` now posts to `/admin/reconciliations`;
unit + E2E mocks updated. Surfaced only because the probe hit the real backend.

### F-2 — No login rate limiting / lockout · severity: medium · recommendation
12 consecutive failed logins all returned 401 with no throttling, lockout, or
backoff. Credential stuffing / brute force is unthrottled at the application
layer. **Recommendation:** add per-IP + per-account throttling (e.g. exponential
backoff or temporary lockout) at the reverse proxy or in `LoginUseCase`. Tracked
for a follow-up; not a data-exposure bug but a real-world hardening gap for a
financial system.

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
docker compose up -d                       # MySQL on :3310
DB_ADAPTER=mysql DB_HOST=127.0.0.1 DB_PORT=3310 DB_NAME=nene_clear \
  DB_USER=nene_clear DB_PASSWORD=nene_clear composer migrations:migrate -- --no-interaction
DB_HOST=127.0.0.1 DB_PORT=3310 php tests/security/seed.php
php -S localhost:8082 sec-router.php &     # MySQL-backed instance
bash tests/security/probe.sh
```
