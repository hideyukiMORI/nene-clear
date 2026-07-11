# Structural-uniformity audit — 2026-07-11 (Clear findings)

On 2026-07-11 a fleet-wide structural-uniformity audit compared the four NeNe
Tier A products (nene-invoice, nene-clear, nene-deal, nene-vault) across six
axes: NENE2 usage, auth/session, multi-tenancy, installer/distribution,
frontend, and the demo machinery. This document records the Clear-relevant
outcome. Every finding below was re-verified against this repository's code on
2026-07-11 (file:line given); nothing here is copied unverified from the
fleet report.

Tracking: [#285](https://github.com/hideyukiMORI/nene-clear/issues/285) (JWT
stack), [#286](https://github.com/hideyukiMORI/nene-clear/issues/286) (NENE2
dependency), [#287](https://github.com/hideyukiMORI/nene-clear/issues/287)
(remaining-findings checklist), [#288](https://github.com/hideyukiMORI/nene-clear/issues/288)
(this document).

## What the fleet shares (Clear included)

The audit confirmed the backbone is uniform across all four products, and
Clear is fully on it:

- NENE2 Router / DI (`ContainerBuilder` + per-domain `ServiceProvider`) /
  RFC 9457 Problem Details / conformance linter in `composer check`.
- `Nene2\Demo` disposable-org consumer: throttle 30/h via
  `FileRateLimitStorage`, capacity 200 + 503, TTL 3 h, fail-close
  `DEMO_MODE`, admin seat with undisclosed random password.
- `Nene2\Install` core (EnvironmentWriter, DatabaseSchemaApplier,
  ReInstallationGuard, ProvisioningProbe) + `public_html` layout + 3-step
  wizard.
- `organization_id` row scoping on every tenant query, `organizations.slug`
  unique, zero demo-special branches in the query layer.
- Shared-hosting Authorization-strip countermeasure (`X-Authorization` dual
  header + `AuthorizationHeaderFallback`, applied in
  `public_html/index.php:181`).
- Frontend core stack: React 19 + Vite 8 + Tailwind 4 + TanStack Query +
  zod, single fetch module (`frontend/src/api/client.ts`).

## Where Clear leads the fleet (strengths — keep these)

| Strength | Evidence |
| --- | --- |
| **Best login-throttle spec of the four products**: email+IP scoped, 5 attempts / 15 min window, 15-min lock, identifiers hashed before storage (invoice: IP-only 10/5 min no lock; deal/vault: none) | `src/Auth/PdoLoginThrottle.php:20-22` |
| **Login timing equalization**: unknown email verifies against a dummy bcrypt hash so response time is not a user-enumeration oracle — unique in the fleet | `src/Auth/LoginUseCase.php:17,37` |
| **Login audit events**: `login_failed` / `login_succeeded` recorded through `Nene2\Audit` — the only product that audits login outcomes | `src/Auth/LoginUseCase.php:44-55,78-89` |
| **MFA (TOTP) backend shipped**: RFC 6238 generator, encrypted secret + hashed recovery codes, replay protection + lockout, 300-s login challenge JWT — no other product has MFA at all (invoice: design only) | `src/Mfa/TotpAuthenticator.php`, `src/Auth/MfaChallengeTokens.php:23` (frontend = slice 4 of #195) |
| **Demo sweep UTC regression tests**: JST/UTC pinned tests for the sweep timezone trap (#280) — invoice and deal lack this | `tests/Demo/` |
| **MySQL `LIKE` escape in demo slug counting** (`ESCAPE '\|'`, #277) — invoice has not adopted this | demo repository queries |
| **JWT fail-close achieved** (by a non-standard mechanism): empty/unset secret → health-only surface, no admin routes mounted | `src/Http/ApplicationFactory.php:117` |

## Findings (divergences from the fleet standard)

High-impact items have dedicated issues; the rest are a checklist in #287.

### High — dedicated issues

1. **JWT layer is bespoke, not the fleet standard** (#285).
   `firebase/php-jwt: ^7` direct dependency (`composer.json:9`), bespoke
   `JwtTokenService` (`src/Auth/JwtTokenService.php`), self-made
   `TokenIssuerInterface` shadowing the upstream one
   (`src/Auth/TokenIssuerInterface.php:9`), product-specific env
   `NENE_CLEAR_JWT_SECRET` (`public_html/index.php:49`). Zero uses of
   `GuardedJwtSecretResolver` / `LocalBearerTokenVerifier` /
   `NENE2_ALLOW_DEV_SECRET` — the other three products use all of them with
   `NENE2_LOCAL_JWT_SECRET`. Not a vulnerability (see fail-close strength
   above); it is a standardization gap.
2. **NENE2 consumed as a path repo `@dev`** (#286).
   `composer.json:10,31-40` (path `../NENE2`, symlink, minimum-stability
   dev); lock pinned to `dev-main` ref `b1a8124` (2026-07-02,
   **pre-v1.10.0**). Releases are not reproducible, and the existing
   `build/release/nene-clear-0.1.0.zip` (2026-07-10 01:26) vendors a NENE2
   without the v1.10.0 demo error-page renderer — demo throttle/capacity
   errors through that artifact render as raw Problem Details JSON.
   invoice/deal pin Packagist `^1.10`.

### Tracked in the checklist issue (#287)

3. Bespoke `NeneClear\Mfa\TotpAuthenticator` core duplicates NENE2 v1.10.0
   `Nene2\Auth\TotpAuthenticator` / `RecoveryCodes`
   (`src/Mfa/TotpAuthenticator.php:21`). — medium
4. `Nene2\Config\ConfigLoader` unused; 221-line composition root in
   `public_html/index.php` (Dotenv direct at `:43`). — medium
5. No branded `DemoBrowserErrorPage`; falls back to upstream
   `MinimalDemoErrorPageRenderer` (other three products wire branded pages
   with a 429 countdown). — low/medium, prerequisite #286
6. `composer check` lacks openapi/mcp validation gates
   (`composer.json:56-63`; `docs/openapi/openapi.yaml` and
   `docs/mcp/tools.json` are unvalidated). — medium
7. Org scoping hand-propagated as `AuthContext::organizationId($request)
   ?? 0` in **38** handler sites; superadmin `org=null` coerces to `0`
   instead of an explicit decision. invoice/deal inject the scope into
   repository constructors so it cannot be forgotten. — high
8. Zero tenant-resolution tests (`tests/Auth/` covers JWT/login/throttle/
   capability/role only). — medium
9. Frontend divergence: classic `src/{api,components,...}` layout (fleet:
   FSD-style), no OpenAPI codegen (hand-written
   `frontend/src/api/endpoints.ts`), module-global non-React-connected
   `t()` (`frontend/src/locales/index.ts:16-20`), E2E as a separate root
   package `tests/e2e/`. — medium
10. Double font delivery: @fontsource self-host (`frontend/src/main.tsx:1-4`)
    plus Google Fonts CDN (`frontend/index.html:8-10`). — low/medium
11. Installer leaves `install.php` in place (guard + manual-delete
    instruction, `public_html/install.php:982`); fleet direction is
    self-delete (deal/vault) + guard as defense-in-depth. — medium

## Notes on accuracy

- The earlier fleet-wide statement "all products use
  `GuardedJwtSecretResolver`" is **incorrect for Clear** — corrected by this
  audit. Clear's fail-close is real but achieved differently (finding 1).
- The fleet report estimated "15+" `?? 0` sites; the verified count is
  **38** (finding 7).
- Clear's MFA is "shipped" at the backend level (slices 1–3 of #195);
  frontend + break-glass CLI remain open as slice 4.
