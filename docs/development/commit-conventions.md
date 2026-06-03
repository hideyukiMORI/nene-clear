# Commit Message Conventions

NeNe Clear uses Conventional Commits, inherited from [NENE2](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/commit-conventions.md) with English descriptions per [ADR 0008](../adr/0008-english-only-repository-documentation.md).

## Format

```text
<type>(<optional scope>): <description> (#<issue>)

[optional body]

[optional footer]
```

## Language

- Keep `type`, `scope`, `BREAKING CHANGE`, and other Conventional Commits keywords in **English**.
- Write the **description and body in English**.
- Include the related GitHub Issue number in the **subject** for all work.

Example:

```text
docs(governance): align issue-driven workflow with NENE2 upstream (#1)
```

```text
feat(reconciliation): add bank CSV import and confirm-match UseCase (#12)
```

## Issue number

| Situation | Rule |
| --- | --- |
| Normal work | Subject **must** include `(#issue)` |
| Docs-only follow-up on same Issue | Reuse the same Issue number |
| Work fully resolves an Issue | Add a closing keyword footer: `Closes #issue` |

If you start editing without an Issue, **stop and create one first** — see `docs/workflow.md`.

### Closing keyword vs. `(#issue)`

The `(#issue)` reference in the subject only **links** the commit to the Issue — it does
**not** close it. To close an Issue, add an explicit `Closes #NN` (or `Fixes` / `Resolves`)
footer in the **commit body**:

```text
feat(dunning): dunning pause per invoice (#98)

Closes #98
```

This matters because PRs are often brought onto `main` via **rebase**, in which case the PR
is `closed` (not `merged`) and GitHub's PR-based auto-close never fires. A closing keyword in
the commit message reaches `main` regardless, and the `close-issues.yml` workflow acts on it.
See `docs/workflow.md` → "Closing Issues on Merge".

## Common Types

| Type | Use |
| --- | --- |
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `refactor` | Code change without feature or bug fix |
| `test` | Test additions or changes |
| `build` | Dependency or build setup |
| `ci` | CI configuration |
| `chore` | Maintenance |

## Body

Use the body when the reason is not obvious from the subject. Explain why the change exists, what trade-off was chosen, and whether follow-up work remains.

## Breaking Changes

Use `!` or a `BREAKING CHANGE:` footer when public API, configuration, CLI, or documented behavior changes incompatibly.

Public API changes must also update OpenAPI and, when applicable, `docs/mcp/tools.json`.
