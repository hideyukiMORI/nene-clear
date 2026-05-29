# Inheritance from NENE2

NeNe Clear inherits engineering governance from [NENE2](https://github.com/hideyukiMORI/NENE2). This document is the source of truth for what is inherited, what is adapted, and what is NeNe Clear–specific.

## Relationship

| Layer | Repository | Role |
| --- | --- | --- |
| Framework runtime | [NENE2](https://github.com/hideyukiMORI/NENE2) | HTTP runtime, DI, middleware, Problem Details, OpenAPI/MCP patterns |
| Application platform | **NeNe Clear** (this repo) | Quote, invoice, payment, client, PDF, admin UI |
| Sibling products | [nene-records](https://github.com/hideyukiMORI/nene-records), [nene-corpus](https://github.com/hideyukiMORI/nene-corpus), [nene-concierge](https://github.com/hideyukiMORI/nene-concierge) | Optional upstream HTTP integrations |
| Reference trials | [NENE2-FT](https://github.com/hideyukiMORI/NENE2-FT) | Patterns and friction notes from field trials |

NeNe Clear is a **consumer project**, not a fork of NENE2. Framework code stays in NENE2; product code stays here.

## Inherited by policy (same rules)

These policies are adopted with the same intent as NENE2 and sibling NeNe products. Local copies live in this repository.

| Topic | Local document |
| --- | --- |
| Issue-driven workflow | `docs/workflow.md` |
| Conventional Commits | `docs/development/commit-conventions.md` |
| Self-review before PR | `docs/development/self-review.md` |
| ADR operation | `docs/development/adr.md` |
| AI agent workflow | `docs/integrations/ai-tools.md`, `AGENTS.md` |
| Cursor summaries | `.cursor/rules/` |

## Inherited by reference (framework behavior)

When implementing HTTP, middleware, validation, or error responses, follow NENE2 upstream docs unless NeNe Clear records an explicit deviation in an ADR.

| Topic | NENE2 upstream |
| --- | --- |
| HTTP runtime (PSR-7/15/17) | `docs/development/http-runtime.md` |
| Middleware order and security | `docs/development/middleware-security.md` |
| Request validation layers | `docs/development/request-validation.md` |
| Problem Details errors | `docs/development/api-error-responses.md` |
| Authentication boundaries | `docs/development/authentication-boundary.md` |
| OpenAPI conventions | `docs/integrations/openapi.md` |
| MCP tool policy | `docs/integrations/mcp-tools.md`, `docs/explanation/why-mcp.md` |
| Database adapter boundaries | `docs/development/database-migrations.md` |
| Domain / use case layering | `docs/development/domain-layer.md` |

Install NENE2 as a Composer dependency and treat `vendor/hideyukimori/nene2/docs/` as the framework reference during development.

## Adapted for NeNe Clear

| Topic | NeNe Clear choice |
| --- | --- |
| Product goal | API-first quote and invoice platform (not general accounting) |
| Public Problem Details base URL | `https://nene-clear.dev/problems/` |
| Coding standards | `docs/development/coding-standards.md` — NENE2 baseline + billing additions |
| Backend standards | `docs/development/backend-standards.md` — PHP/API strict policy |
| Monetary values | Integer **cents** in DB and JSON; no floats |
| Language policy | **English** for all repository docs, Issues, PRs, commits, OpenAPI, API errors (ADR 0008); admin UI **ja + en** only (ADR 0005) |
| Review checklists | `docs/review/` — task-specific lists for this product |

## NeNe Clear–specific (not inherited)

Record these in ADRs or product docs when they stabilize:

- Client / quote / invoice / payment domain model
- Japan qualified invoice field validation rules
- Payment reconciliation and dunning compliance rules
- PDF generation strategy (server-side, no client-side tax calculation)
- Admin frontend vs public document download boundaries
- MCP tool catalog for billing operations
- Release versioning of the NeNe Clear product (`v0.x` until first stable API)

## When upstream and local docs conflict

1. Update the **local source-of-truth doc** in this repository first.
2. If the conflict is about **framework behavior**, prefer NENE2 upstream unless an ADR documents a deliberate deviation.
3. Keep `.cursor/rules/` as a short summary; do not duplicate full policy text there.

## Verification commands (once runtime is scaffolded)

NeNe Clear should expose the same quality gates as NENE2 consumer projects:

```bash
composer check
composer openapi
composer mcp
```

When `frontend/` exists:

```bash
npm run check --prefix frontend
```
