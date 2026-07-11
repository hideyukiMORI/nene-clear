# NENE2 Compliance — Binding Coding Rules

**Status: binding (non-negotiable).** NeNe Clear is a **NENE2 consumer project**.
It MUST follow NENE2's coding rules and conventions. This document maps each
NENE2 policy to Clear's binding rule and pins the **concrete framework symbols**
Clear code must reuse. A change that violates a rule here **blocks merge**.

**Authority / conflict resolution:**

- For **framework concerns** (HTTP runtime, DI, middleware, errors, validation
  shape, pagination, persistence boundary, auth), **NENE2 upstream wins**.
  Deviations require a local ADR (`docs/development/adr.md`).
- For **domain concerns** (identifiers, money, reconciliation/dunning, legal
  posture), Clear's [`terminology.md`](../explanation/terminology.md),
  [`scope-contract.md`](../explanation/scope-contract.md), and the compliance
  docs win.
- The upstream source of truth is the NENE2 repo `docs/development/` and
  `docs/integrations/`. Treat `vendor/hideyukimori/nene2/docs/` as the reference
  once installed. This file is the **local mapping**, not a fork of it.

See also: [`inheritance-from-nene2.md`](../inheritance-from-nene2.md),
[`backend-standards.md`](./backend-standards.md),
[`frontend-standards.md`](./frontend-standards.md),
[`naming-conventions.md`](./naming-conventions.md),
[`coding-standards.md`](./coding-standards.md).

---

## 1. Baseline (NENE2 `coding-standards.md`)

- PHP **`>=8.4`**; every PHP file starts with `declare(strict_types=1);`.
- **PSR-12** unless a narrower rule says otherwise. No file-level banners.
- Prefer **immutable value objects / `readonly`**; native types, enums, small
  DTOs at boundaries — never unstructured arrays.
- No framework magic that hides control flow from tests, static analysis, or AI.
- Money is **integer cents** (`*_cents`), JPY only Phase 1–3. No float/DECIMAL.
- Repository docs, OpenAPI text, API error metadata: **English** (ADR 0008).
- Quality tools: **PHPUnit, PHPStan, PHP-CS-Fixer**; verified via `composer check`.

---

## 2. Project layout & namespace (NENE2 `project-layout.md`)

- PHP namespace root: **`NeneClear\`** (`composer.json` PSR-4 → `src/`).
- `src/` framework-style folders mirror NENE2 where Clear extends them; Clear's
  **application code is grouped by domain concept, not by layer**
  (no `src/Handlers/`, `src/Repositories/`, `src/UseCases/`).
- Standard directories (same as NENE2): `src/`, `tests/`, `config/`,
  `database/{migrations,seeds,schema}/`, `templates/`, `public_html/`
  (`index.php` front controller + built `assets/`), `frontend/`, `docs/`.
- **Only `public_html/` is web-exposed.** Never place `src/`, `vendor/`, `.env`,
  frontend source, or tests under it.
- Clear's domain folders (`src/`): `Organization/`, `Auth/`, `User/`,
  `ClearSettings/`, `BankImport/`, `Reconciliation/` (includes client credit),
  `Dunning/`, `InvoiceUpstream/`, `Audit/`, `I18n/`, `Http/`
  (see [`backend-standards.md`](./backend-standards.md) §2).

---

## 3. Canonical request flow (NENE2 `domain-layer.md`)

Every feature follows the same path. Business logic lives **only** in the UseCase.

```text
HTTP handler (thin)
  → UseCase (application logic, business invariants)
    → RepositoryInterface (data access contract)
      → Pdo{Entity}Repository (persistence detail)
```

| Layer | MUST | MUST NOT |
| --- | --- | --- |
| **Handler** | parse PSR-7 input, build readonly DTO, call UseCase, map response | business rules, SQL, direct upstream HTTP, calling repositories |
| **UseCase** | business invariants, allocation/dunning rules, orchestration; one `execute()` method | `$_SERVER`/`getenv`, PSR-7, PDO, container as service locator |
| **RepositoryInterface** | domain-verb methods (`findById`, `save`); domain types in/out | leak PDO rows / raw arrays |
| **Pdo{Entity}Repository** | all SQL, via `DatabaseQueryExecutorInterface`; cast row types | business rules, HTTP |
| **Upstream client** | HTTP to Invoice behind an interface | domain invariants, SQL |

Rules (verbatim from NENE2):

- One method per UseCase interface, always **`execute`**; input/output are
  **typed readonly DTOs**, never raw arrays or PSR-7 objects.
- Constructor injection only; no `new` for testable dependencies.
- Nullable return (`?Entity`) for valid "not found"; throw only when absence is a
  programming error.
- **Group by domain concept**, not layer type.

---

## 4. Naming (NENE2 `coding-standards.md` + Clear `naming-conventions.md`)

| Role | Pattern | Clear example |
| --- | --- | --- |
| Handler | `{Verb}{Noun}Handler` | `ConfirmMatchHandler` |
| UseCase iface/impl | `{Verb}{Noun}UseCaseInterface` / `…UseCase` | `ConfirmMatchUseCase` |
| Input/Output DTO | `{Verb}{Noun}Input` / `…Output` | `ConfirmMatchInput` |
| Domain entity | singular noun | `BankTransaction` |
| Repository iface / PDO impl | `{Entity}RepositoryInterface` / `Pdo{Entity}Repository` | `PdoBankTransactionRepository` |
| Domain exception | `{Entity}{Reason}Exception` | `BankTransactionNotFoundException` |
| Service provider | `{Purpose}ServiceProvider` | `ReconciliationServiceProvider` |

Methods camelCase; constants UPPER_SNAKE_CASE; classes `final` (+ `readonly`
where applicable). Tables snake_case **plural**; JSON property names snake_case;
`operationId` camelCase. **Exact identifiers are governed by
[`terminology.md`](../explanation/terminology.md) (SSOT) — register before use.**

---

## 5. Validation layering (NENE2 `request-validation.md`)

```text
Middleware:  size / content-type / JSON parse / auth / CORS / request id
Handler:     path/query/body mapping → readonly DTO → format & semantic checks
UseCase:     business invariants, state-dependent & authorization-sensitive rules
```

- Convert HTTP input to readonly DTOs **before** calling the UseCase; never pass
  raw request arrays inward.
- Format-validation failures throw `Nene2\Validation\ValidationException` built
  from `Nene2\Validation\ValidationError(field, message, code)` → mapped to
  `validation-failed` Problem Details (`422`) at the error boundary.
- Business invariants live in the UseCase (must hold outside HTTP too — CLI,
  tests, future MCP). Non-trivial mapping goes in a focused `*InputMapper`.

---

## 6. Dependency injection (NENE2 `dependency-injection.md`)

- **PSR-11** boundary; **explicit wiring** via service providers — no autowiring,
  no attribute registration, **no service locator inside UseCase/domain code**.
- Providers are small and grouped by domain concept; implement
  `Nene2\DependencyInjection\ServiceProviderInterface` and register bindings on
  the `ContainerBuilder`. Bind **interfaces** where test substitution matters.
- Register providers through the runtime bootstrap (`RuntimeServiceProvider` /
  `RuntimeContainerFactory` path). Provider order is explicit when it matters.

```php
final class ReconciliationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->bind(BankTransactionRepositoryInterface::class, static fn($c) =>
            new PdoBankTransactionRepository($c->get(DatabaseQueryExecutorInterface::class)));
        $builder->bind(ConfirmMatchUseCaseInterface::class, static fn($c) =>
            new ConfirmMatchUseCase(
                $c->get(BankTransactionRepositoryInterface::class),
                $c->get(InvoiceUpstreamClientInterface::class),
            ));
    }
}
```

---

## 7. Framework objects Clear MUST reuse (do not reinvent)

These exist in NENE2 (`Nene2\…`). Building a parallel version is a defect.

| Concern | Reuse | Notes |
| --- | --- | --- |
| JSON responses | `Nene2\Http\JsonResponseFactory` | success bodies |
| JSON body parsing | `Nene2\Http\JsonRequestBodyParser` / `JsonBodyParseException` | malformed JSON at middleware |
| Problem Details | `Nene2\Error\ProblemDetailsResponseFactory`, `ErrorHandlerMiddleware` | configure base to `https://nene-clear.dev/problems/` |
| Routing | `Nene2\Routing\Router` | path params via `Router::PARAMETERS_ATTRIBUTE`, **not** individual attributes |
| Pagination | `Nene2\Http\PaginationQuery` / `PaginationQueryParser` / `PaginationResponse` | the `items`/`limit`/`offset` list envelope |
| Query string | `Nene2\Http\QueryStringParser` | filters |
| Request-scoped state | `Nene2\Http\RequestScopedHolder` | holds resolved tenant (`organization_id`) |
| Auth | `Nene2\Auth\BearerTokenMiddleware` + `TokenVerifierInterface` / `TokenVerificationException` | JWT (ADR 0008) |
| DB access | `Nene2\Database\DatabaseQueryExecutorInterface`, `DatabaseConnectionFactoryInterface` (`PdoConnectionFactory`), `DatabaseTransactionManagerInterface` | never raw PDO in app code |
| Validation | `Nene2\Validation\ValidationError` / `ValidationException` | |
| Clock | `Nene2\Http\ClockInterface` / `UtcClock` | inject; never `new DateTime()`/`time()` directly in logic |
| Tokens / ETag | `Nene2\Http\SecureTokenHelper`, `ConditionalGetHelper` / `ConditionalWriteHelper` | secure tokens, optimistic concurrency |
| Health | `Nene2\Http\HealthCheckInterface` / `HealthStatus` | `GET /health` |
| Emit | `Nene2\Http\ResponseEmitter` | front controller |

---

## 8. HTTP runtime & middleware (NENE2 `http-runtime.md`, `middleware-security.md`)

- **PSR-7 / PSR-15 / PSR-17**. Explicit, readable route tables. `public_html/index.php`
  is the front controller. No controller-resolver magic.
- Middleware order (explicit, documented), extending the NENE2 baseline:

```text
1. Error handling (wraps all)
2. Request id (X-Request-Id)
3. Security headers
4. CORS (config-driven; prod allowlist)
5. Request size limit
6. Authentication (BearerTokenMiddleware)
7. Organization resolution (tenant → RequestScopedHolder)
8. Capability check (CapabilityMiddleware)
9. (future) OpenAPI/request validation
10. Routing / handler dispatch
```

- Route-specific business validation stays in handlers/UseCases, never global
  middleware. CORS prod allowlist is config-driven; secrets/headers per NENE2.

---

## 9. Errors & Problem Details (NENE2 `api-error-responses.md`)

- All public JSON errors are **RFC 9457 Problem Details**,
  `Content-Type: application/problem+json`.
- `type` base: **`https://nene-clear.dev/problems/{slug}`**; slugs are registered
  in [`terminology.md`](../explanation/terminology.md) §4 — stable, never raw
  exception names.
- Validation → `validation-failed` (`422`) with structured `errors[]`
  (`field`, `message`, `code`).
- Map domain exceptions → Problem Details **at the HTTP error boundary**
  (`ErrorHandlerMiddleware`), never inside UseCases. Never leak stack traces,
  SQL, file paths, secrets, or private identifiers.

---

## 10. Auth, tenancy & capabilities (NENE2 `authentication-boundary.md` + ADR 0006/0008)

- JWT bearer via `BearerTokenMiddleware`; on success read claims from request
  attribute `nene2.auth.claims` (credential type `nene2.auth.credential_type`).
  `401` returns Problem Details + `WWW-Authenticate: Bearer`.
- **Multi-tenant:** an organization-resolution middleware sets the resolved
  `organization_id` into `RequestScopedHolder`; **every repository query is
  org-scoped**. Modes: `single` (default) / `path` / `subdomain` / `custom_domain`.
- **Capabilities** enforced by `CapabilityMiddleware` using Clear's `Role` /
  `Capability` enums (`manage_organizations`, `manage_users`,
  `manage_clear_settings`, `manage_reconciliation`, `view_reconciliation`,
  `send_dunning` — terminology §2).
- Secrets (JWT secret, Invoice upstream token, SMTP) come from `.env` only; never
  logged, never in OpenAPI/MCP/responses.

---

## 11. Database & migrations (NENE2 `database-migrations.md`, `domain-layer.md`)

- **Phinx**; migrations in `database/migrations/` named
  `YYYYMMDDHHMMSS_describe_change.php`; schema snapshots in `database/schema/`.
- **All SQL lives in `Pdo{Entity}Repository`** via `DatabaseQueryExecutorInterface`
  (parameterized); transactions via `DatabaseTransactionManagerInterface`. No raw
  PDO, no SQL in UseCases/handlers.
- Tables snake_case plural; `id` BIGINT PK; FKs `{singular}_id`; money `*_cents`
  BIGINT; `organization_id` on every tenant-scoped table; soft delete
  `is_deleted`/`deleted_at`; **no hard delete** of bank/match/dunning history
  (compliance). See [`phase-1-reconciliation-design.md`](./phase-1-reconciliation-design.md) §2.

---

## 12. OpenAPI & endpoint scaffold (NENE2 `endpoint-scaffold.md`, `request-validation.md`)

OpenAPI 3.1 is the **contract**; an endpoint is "done" only when it exists in all
places. Per-endpoint workflow:

1. Focused Issue.
2. Runtime route + thin handler.
3. OpenAPI path: stable `operationId`, summary/description, `200` schema + `ok`
   example, Problem Details responses (`401`/`403`/`413`/`422`/`500` as relevant),
   security only when matching middleware exists.
4. Tests close to behavior + contract test reading `docs/openapi/openapi.yaml`.
5. New table → Phinx migration + schema snapshot; run migrations before HTTP smoke.
6. `composer check`; then local HTTP smoke.
7. MCP catalog entry only when the tool can safely call the public API (Phase 4).

Shared OpenAPI schemas: `ProblemDetails`, `ValidationProblemDetails`,
`ValidationError`. `operationId` matches OpenAPI ↔ route ↔ `docs/mcp/tools.json`.

---

## 13. Testing (NENE2 `coding-standards.md`, `domain-layer.md`)

- **UseCase unit tests** run with **no DB** — inject in-memory repositories
  implementing the interface (kept in `tests/`, never shipped).
- **Repository adapter tests** exercise real SQL (SQLite in-memory; MySQL via the
  opt-in DB test command).
- **HTTP/contract tests** verify public behavior against OpenAPI.
- Tests deterministic and small; test method names
  `test_{behavior}_when_{condition}`; `tests/` mirrors `src/`.
- Reconciliation tests cover partial / overpayment→credit / fee mismatch /
  reversal; upstream-client tests fake the Invoice API and cover degraded mode.

---

## 14. Frontend (NENE2 `frontend-integration.md`, `view-rendering.md`)

- Stack: **React + TypeScript + Vite**, **npm**, active Node LTS; `engines` +
  `packageManager` in `frontend/package.json`; commit `frontend/package-lock.json`;
  never commit `node_modules/`.
- **Source in `frontend/`**, build output to `public_html/assets/` (generated).
  Never put frontend source in `public_html/`. `public_html/index.php` stays the
  PHP front controller; asset serving never bypasses the API runtime.
- **Data flow (one direction):**

```text
frontend/src/api/   typed fetch wrapper — maps snake_case JSON (no renaming),
                    parses Problem Details into typed errors, injects bearer token
   → frontend/src/hooks/   useX() hooks call the api client, hold fetch state
      → components          render from hooks; NO fetch, NO business logic here
```

- Components are presentational/container-split; **business logic and HTTP live
  outside components** (api client + hooks). Do not rename API fields in transit
  (snake_case JSON ↔ typed TS models).
- Dev: Vite proxies `/api/*` to the Docker backend; apps set
  `VITE_NENE_CLEAR_API_BASE_URL` for a different base. Generated API client is
  considered only after OpenAPI is stable.
- UI strings in **ja (primary) + en (secondary)** locale catalogs only — no
  hardcoded strings, no other locales (ADR 0005). Statutory/outbound Japanese
  text (dunning) stays Japanese regardless of UI locale.
- Commands: `npm run dev|build|check --prefix frontend`
  (`check` = type-check + lint + format).
- Full detail: [`frontend-standards.md`](./frontend-standards.md).

---

## 15. Configuration & secrets (NENE2 `configuration.md`)

- **Typed config objects** at runtime; raw `getenv()`/`$_ENV`/`$_SERVER` only in
  the config loading boundary (`src/Config/` or Clear's settings loader).
- `.env` for local/test (ignored); `.env.example` documents shape; production uses
  real env vars. Clear stores the **name** of the upstream-token env var
  (`upstream_token_ref`), never the secret in DB.

---

## 16. Documentation comments (NENE2 `documentation-comments.md`)

- PHPDoc for public APIs, interfaces, middleware, typed config, extension points.
- TSDoc for exported frontend utilities, hooks, types, api-client helpers.
- Do not repeat native types or obvious detail. Name files/classes after role.

---

## 17. Compliance gate (every PR)

A change in scope of any section above MUST:

1. Follow the rule here and the cited NENE2 upstream doc; **placement violations
   block merge**.
2. State any framework-behavior deviation as a local ADR (`docs/development/adr.md`).
3. Pass `composer check` (and `npm run check --prefix frontend` for frontend).
4. Run the matching self-review checklist ([`../review/`](../review/)).

If unsure whether NENE2 already provides something, **check `vendor/hideyukimori/nene2/docs/`
and `src/` first** and reuse it (§7).

---

## NENE2 upstream references

`docs/development/`: `coding-standards.md`, `project-layout.md`, `domain-layer.md`,
`dependency-injection.md`, `http-runtime.md`, `middleware-security.md`,
`request-validation.md`, `api-error-responses.md`, `authentication-boundary.md`,
`database-migrations.md`, `endpoint-scaffold.md`, `frontend-integration.md`,
`view-rendering.md`, `configuration.md`, `documentation-comments.md`,
`quality-tools.md`. `docs/integrations/`: `openapi.md`, `mcp-tools.md`.
ADRs: `0007-put-vs-patch-policy`, `0008-jwt-authentication`, `0010-rate-limiting`.

---

## Local framework development (NENE2 checkout)

`hideyukimori/nene2` is consumed from **Packagist as a tagged dist** (`^1.10`).
Do **not** commit a path repository or `@dev` constraint back into
`composer.json` — that made releases non-reproducible and shipped whatever the
local NENE2 checkout happened to contain (#286).

To hack on NENE2 and Clear together, switch your working tree to the sibling
checkout locally and revert before committing:

```bash
# switch to the local NENE2 checkout (working tree only — do not commit)
composer config repositories.nene2-local path ../NENE2
composer config repositories.nene2-local.options.symlink true
composer require hideyukimori/nene2:@dev

# ...develop against ../NENE2 HEAD...

# revert to the tagged Packagist dist before committing
git checkout composer.json composer.lock
composer install
```

Release builds resolve their vendor from `composer.json` + `composer.lock` in
a clean staging tree and fail on any symlink, so a forgotten override cannot
ship.

Last updated: 2026-07-11
