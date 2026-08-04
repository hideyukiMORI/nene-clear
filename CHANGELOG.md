# Changelog

Notable changes to **NeNe Clear** (payment reconciliation and dunning management).

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
versions follow [Semantic Versioning](https://semver.org/).

English only, per [ADR 0008](docs/adr/0008-english-only-repository-documentation.md).

## Scope of this file

This changelog starts on **2026-08-04** and records releases from that point
forward. It is deliberately **not** back-filled.

Everything before this date is recorded in the git history, which is the
authoritative record for that period — `git log`, the merged PRs, and the issues
they close. Reconstructing 253 commits of history into release notes after the
fact would mean writing entries nobody verified against what actually shipped,
and a changelog that cannot be trusted is worse than one that starts honestly at
a date. For the design decisions behind that history, read
[`docs/adr/`](docs/adr/); for what is being worked on now, see the private
`nene-origin/internal-docs/clear/todo/current.md`.

## [Unreleased]

### Changed

- **Dependency-vulnerability gate: `brace-expansion` patched instead of
  excepted** (#407). `GHSA-rgw5-rvv9-x895` is resolved by a lockfile-only bump to
  2.1.4 / 5.0.9 — no `package.json` change (the existing `brace-expansion@5`
  caret already covered it) and no new allowlist entry.

### Removed

- **Stale audit exception `GHSA-mh99-v99m-4gvg`** (#407). The entry claimed the
  2.x branch had no patched release; the GitHub Advisory API shows its 2.x fix is
  2.1.3 — the version this tree was already on — so the exception had stopped
  matching anything. The allowlist is now `react-router` alone.
- **`docs/journal/`** (#410). The six pre-2026-07-17 work logs moved to the
  private operational-log home (`nene-origin/internal-docs/clear/daily/`),
  completing the P3 migration that had left them behind. Public `docs/` now holds
  only Diátaxis, ADR, and this changelog.

[Unreleased]: https://github.com/hideyukiMORI/nene-clear/commits/main
