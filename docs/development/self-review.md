# Self-Review Checklist Policy

NeNe Clear uses self-review checklists before push or PR, inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/self-review.md).

## How to use

1. Identify the work type.
2. Open the matching checklist in `docs/review/`.
3. Review every applicable item.
4. Run the narrowest useful verification.
5. Mention the checklist in the PR body.

Example:

```text
Self-review: backend-api, openapi-contract
```

If an item is not applicable, mark it mentally as `N/A`. Do not delete checklist items to pass review.

## Checklist files

| File | Use for |
| --- | --- |
| `compliance.md` | **Binding.** Any change touching quotes, invoices, payments, tax, numbering, PDF, or retention |
| `backend-api.md` | Endpoints, handlers, validation, HTTP behavior |
| `openapi-contract.md` | OpenAPI schemas, examples, contract tests |
| `database.md` | Migrations, repositories, soft delete |
| `middleware-security.md` | Auth, CORS, logging, rate limits |
| `docs-policy.md` | Workflow, ADRs, roadmap, Cursor rules |
| `frontend.md` | **Phase 2 — not in repo yet.** Admin React/TypeScript |

Do **not** use `frontend.md` until Phase 2 creates `frontend/`. Until then, skip that checklist or mark `N/A`.

## AI agents

Pick relevant checklists before finalizing changes. Do not claim an item passed if it was not checked.

If no checklist matches, use `docs/workflow.md`, `docs/development/coding-standards.md`, and `docs/inheritance-from-nene2.md` directly.
