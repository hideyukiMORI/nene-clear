# Demo Environment Runbook

One page for running, showing, and resetting the NeNe Clear prospect demo
(#260). The demo is a **fixed, pre-seeded organization** with hand-out
credentials — no self-signup, no auth-code changes. Reset = rerun the seeder.

## 1. Prepare

```bash
composer install
composer migrations:migrate
(cd frontend && npm ci && npm run build)   # SPA into public_html/assets/ (git-ignored)
docker compose up -d mailpit               # local: catches dunning mail on :8383
```

`.env` (see `.env.example` for the full comments):

```dotenv
NENE2_LOCAL_JWT_SECRET=<64 hex chars>           # required — fail-close without it (#285)
NENE_CLEAR_DEMO_UPSTREAM=1                      # live upstream suggestions + dunning
NENE_CLEAR_DEMO_ADMIN_PASSWORD="…12+ chars…"    # quote values containing #
NENE_CLEAR_DEMO_VIEWER_PASSWORD="…12+ chars…"
SMTP_HOST=127.0.0.1                             # local Mailpit; empty = log-only mailer
SMTP_PORT=1383
```

## 2. Seed / reset

```bash
php tools/seed-demo.php --force
```

Reads the demo passwords from `.env` (or `--admin-password` / `--viewer-password`).
**Destructive for the demo org only**: deletes every record of the org with slug
`demo` (including its audit trail) and reseeds ~280 bank transactions, 12 monthly
import batches, reconciliations, credits, manual receivables, dunning history and
an audit trail — all dated relative to the run date, so the demo never looks
stale. Rerunning it at any time (or nightly via cron) is the reset mechanism.

It also rewrites `var/demo-bank-import.csv` — the file you import live during
the demo.

## 3. Run

```bash
php -S 0.0.0.0:8384 -t public_html public_html/index.php
```

- URL: `http://localhost:8384/` (built SPA is served from `public_html/assets/`)
- Sign-in: `demo-admin@nene-clear.dev` (full flow) / `demo-viewer@nene-clear.dev`
  (read-only screens) with the passwords from `.env`
- Mailpit UI (sent dunning mail): `http://localhost:8383/`

## 4. Showcase walkthrough (the 見せ場)

1. **Dashboard** — unmatched count and recent dunning are non-zero out of the box.
2. **Bank import** — upload `var/demo-bank-import.csv` against the みずほ銀行
   account. 6 deposits import; the withdrawal row is filtered out.
3. **Name-mismatch match (名義ズレ)** — open the new unmatched deposit
   `ヤマダコウムテン（カ` (¥423,500) and propose: the engine suggests manual
   receivable **MR-2026-012 / 株式会社山田工務店** on *exact amount + due soon*
   even though the transfer name doesn't contain the client name. Confirm — the
   receivable flips to `paid`, the transaction to `matched`, and the audit log
   records the confirmation.
4. **Upstream suggestion** — deposit `ヤマカワショウジ（カ` (¥660,000) proposes
   upstream invoice **INV-2026-058** (from the demo Invoice fixture).
5. **Dunning** — the eligible list shows the fixture invoices. Send an initial
   notice for overdue **INV-2026-056**; the mail lands in Mailpit. Sending again
   for **INV-2026-057** is rejected with *dunning-too-frequent* (a notice went
   out 2 days ago — the 7-day minimum interval at work). **INV-2026-059** is
   paused ("支払計画合意済み") to show the pause feature.
6. **Audit log** — every step above appears immediately at the top.
7. **Reset** — rerun `php tools/seed-demo.php --force`; everything is clean again.

## 4.5 Disposable demo orgs (`/demo/standard`, #275)

With `DEMO_MODE=1` in `.env`, `GET /demo/standard` provisions a **fresh
throwaway organization per visit** — hand the URL to a prospect and they get
their own seeded playground (the invoice-style flow, adopted via
`Nene2\Demo` v1.9.0):

- The visitor lands signed in (a seat page stores a normal 1-hour access token
  in the SPA session; when it expires, they simply visit the demo URL again).
- The dataset is a compact T-relative variant of the fixed org's: ~60 deposits
  over 3 months, dunning history, and the 名義ズレ showcase **pre-staged** —
  open receivables and their exact-amount deposits already exist, so
  propose → confirm works immediately with no CSV upload.
- Protections: per-IP throttle (30 starts/hour → 429 + Retry-After),
  instance-wide ceiling (`DEMO_MAX_ORGS` → 503), fail-close `DEMO_MODE`
  (unset/typo → 404). Only `/demo/standard` is public; any other `/demo/*`
  path stays behind auth.
- Cleanup: `php tools/sweep-demo.php` (hourly cron) expires orgs past
  `DEMO_TTL_HOURS` and reaps overflow. Only slugs prefixed `demo-` are ever
  touched — the fixed `demo` org and real tenants are invisible to it.

The fixed org (§2–4) remains the guided-demo star; disposable orgs are the
"URL を配るだけ" channel.

## 5. Known limitations

- **The Invoice upstream is a fixture, not a live NeNe Invoice.**
  `NENE_CLEAR_DEMO_UPSTREAM=1` fills the in-memory fake client per request:
  confirming a match against an *upstream* invoice does not reduce that
  invoice's outstanding on the next screen load (Clear's own reconciliation
  records are persistent and correct). Prefer the manual-receivable matches for
  the confirm showcase; treat upstream rows as "connected to Invoice" texture.
- **MFA stays off** for the demo org — the opt-in flow's frontend + break-glass
  CLI are still open (slice 4 of #195); do not enroll TOTP on demo accounts.
- **Re-importing the same CSV the same day** returns a duplicate-import 409 —
  by design (dedupe by file hash). After a reset the import works again.
- The bearer token lives in the SPA session; a hard reload keeps the session
  per tab (sessionStorage) but a new tab needs a fresh sign-in.

## 6. Shared-hosting (HETEML) deployment sketch

Target: `clear.ayane.co.jp` (same shape as invoice; decided 2026-07-09; DB
`_nene_clear` on the shared MySQL — one product = one database, no table
sharing). Publishing itself is a separate owner decision; the pieces are ready:

1. Build the artifact on the dev machine: `composer release:zip`
   (`tools/build-release.sh` — vendor `--no-dev`, SPA built into
   `public_html/assets/`, installer included).
2. Upload/rsync so that only the app's `public_html/` is inside the docroot
   (vendor one level above, like invoice).
3. Run `public_html/install.php` once (requirements → DB → migrate → first
   admin), then **delete it**.
4. `.env` on the host: MySQL `_nene_clear` credentials, `APP_DEBUG=false`,
   real `NENE2_LOCAL_JWT_SECRET`, demo variables as above; HETEML SMTP or leave
   `SMTP_HOST` empty (log-only) until deliverability is decided.
5. Seed: `php8.4 tools/seed-demo.php --force` over SSH; add a daily cron with
   the same command as the reset.

The repo's "shared hosting is not recommended" stance (roadmap Phase 3, #193)
is a **data-sensitivity judgment for production deployments** (receivables /
bank / PII: no root, throttled SMTP, weak backup story) — not a technical
blocker. A demo org holds only fictional seed data, is reset nightly, and sends
mail to `.example` addresses, so none of those risks apply to this deployment.
Production installs should still go to VPS + Docker (Tier B).

## Migration runbook — JWT secret env rename (#285, owner's step)

Since #285 the canonical env name is the fleet-standard
`NENE2_LOCAL_JWT_SECRET`. The deployed demo's `.env` still says
`NENE_CLEAR_JWT_SECRET`; the app reads it as a **transitional fallback for one
release**, so nothing breaks on deploy. To finish the migration on HETEML
(production `.env` edits are the owner's step — never automated):

1. SSH in and edit the deployment `.env`: rename the key
   `NENE_CLEAR_JWT_SECRET=` → `NENE2_LOCAL_JWT_SECRET=` **keeping the same
   value** (same value ⇒ already-issued tokens keep verifying; the demo's 1 h
   sessions are unaffected).
2. No other change: `NENE2_ALLOW_DEV_SECRET` must stay **unset** in
   production (the resolver ignores it there anyway — hard fail-close).
3. Verify: `curl -s https://clear.ayane.co.jp/health` is `ok`, then start a
   `/demo/standard` session and confirm the SPA lands authenticated.
4. Rollback: rename the key back. The fallback read stays until the next
   release removes it.
