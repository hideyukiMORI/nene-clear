# Coding Standards

NeNe Clear coding standards split by surface. **Full policies live in the dedicated documents below** — this file is the index.

| Surface | Source of truth |
| --- | --- |
| **PHP / API / database** | [`backend-standards.md`](./backend-standards.md) |
| **Naming (code, API, DB, tests)** | [`naming-conventions.md`](./naming-conventions.md) |
| **Canonical term/identifier spellings** | [`../explanation/terminology.md`](../explanation/terminology.md) |
| **Product term meanings (glossary)** | [`../explanation/glossary.md`](../explanation/glossary.md) |
| **React / TypeScript admin** | [`frontend-standards.md`](./frontend-standards.md) (Phase 2+) |
| **NENE2 inheritance map** | [`../inheritance-from-nene2.md`](../inheritance-from-nene2.md) |

**Framework baseline:** [NENE2 coding standards](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/coding-standards.md) — NeNe Clear deviates only where local docs or ADRs say so.

---

## Shared rules (all surfaces)

- **Naming conventions are absolute (non-negotiable).** Violations and typos block merge — see [`naming-conventions.md`](./naming-conventions.md).
- **Terminology has one source of truth:** [`../explanation/terminology.md`](../explanation/terminology.md). Every identifier must match it exactly; adding/renaming a term updates the registry in the same PR.
- **Zero typos in identifiers.** Spelling variants of registered terms are defects, not style preferences.
- GitHub Issue-driven work; focused PRs; no direct commits to `main`
- **Strict typing** at boundaries — PHP readonly DTOs; TypeScript strict mode (when frontend exists)
- **OpenAPI** is the public API contract; MCP maps to the same HTTP operations
- Application Problem Details `type`: `https://nene-clear.dev/problems/{problem-name}`
- **Monetary values:** integer cents everywhere — no floats in DB or JSON
- **Placement violations block merge** — see backend standards
- Public docs, OpenAPI text, and API error metadata: **English**
- Issues, PRs, commits, `.cursor/rules/`: **Japanese allowed**
- **Never integrate billing into sibling products** — ADR 0002

---

## Backend (summary)

Full policy: **`docs/development/backend-standards.md`**. Naming: **`docs/development/naming-conventions.md`**.

- NENE2 consumer — framework in `vendor/`, product in `src/`
- Domain-grouped modules — not layer folders
- Handler → UseCase → RepositoryInterface → PdoRepository
- No PDO/SQL outside `Pdo*Repository`; no business logic in handlers
- Phinx migrations + `database/schema/` snapshots
- PHPUnit: in-memory use cases, SQLite repositories, OpenAPI contract tests
- `composer check` before merge

---

## Verification

```bash
composer check
composer openapi
composer mcp
```

When `frontend/` exists:

```bash
npm run check --prefix frontend
```
