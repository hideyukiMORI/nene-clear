# Contributing

NeNe Clear is built through small, Issue-driven changes. This document is the shared entry point for humans and AI agents.

## Required Reading

| Topic | Document |
| --- | --- |
| NENE2 inheritance map | `docs/inheritance-from-nene2.md` |
| Sibling product boundaries | `docs/integrations/sibling-products.md` |
| Workflow | `docs/workflow.md` |
| Coding standards | `docs/development/coding-standards.md` |
| Naming conventions | `docs/development/naming-conventions.md` |
| Glossary | `docs/explanation/glossary.md` |
| Backend standards (PHP/API) | `docs/development/backend-standards.md` |
| Commit messages | `docs/development/commit-conventions.md` |
| AI tools | `docs/integrations/ai-tools.md` |
| Agent entry point | `AGENTS.md` |
| Roadmap | `docs/roadmap.md` |
| Current work | `docs/todo/current.md` |

## Collaboration Policy

Follow [`docs/workflow.md`](workflow.md) — inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/workflow.md):

1. Create or reuse a GitHub Issue **before** editing.
2. Branch from `main` as `type/issue-number-summary`.
3. Implement, verify (`composer check` when applicable), commit with `(#issue)`.
4. Push, open PR with `Closes #number`, merge after checks — **do not push directly to `main`**.

- Use one branch and one PR per focused work unit.
- Keep `docs/milestones/`, `docs/roadmap.md`, and `docs/todo/current.md` updated when direction changes.
- Explain intent, impact, verification, and remaining risk in PRs.
- Prefer documentation that helps the next developer or AI agent decide what to do without rereading chat history.

## Secrets

Do not commit passwords, tokens, private URLs, production credentials, or local `.env` files. Commit only non-secret examples such as `.env.example` when needed.

Sensitive keys for this product include:

- Admin JWT secrets
- Upstream API bearer tokens (NeNe Records, NeNe Concierge)
- SMTP credentials for invoice email delivery

## Engineering Theme

NeNe Clear should stay readable, secure, and self-hostable:

- strict, typed, explicit boundaries (inherited from NENE2)
- decoupled use cases and infrastructure
- OpenAPI contracts before client assumptions
- Japan invoice compliance fields validated at API layer
- MCP access only through documented HTTP boundaries
- **never** merge into or embed inside NeNe Records (ADR 0002)

## Upstream Framework

Runtime HTTP, middleware, and DI patterns come from [NENE2](https://github.com/hideyukiMORI/NENE2). When framework behavior is unclear, read NENE2 docs under `vendor/hideyukimori/nene2/docs/` after `composer install`.
