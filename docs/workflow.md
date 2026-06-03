# Workflow

NeNe Clear uses GitHub Issues for work tracking and local Markdown for project memory. This workflow inherits [NENE2 `docs/workflow.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/workflow.md) with the substitutions below.

See also: `docs/inheritance-from-nene2.md`.

## Standard Flow

1. Create or reuse a focused GitHub Issue.
2. Confirm context in `docs/roadmap.md`, `docs/milestones/`, and `docs/todo/current.md`.
3. Create a branch from `main` named like `type/issue-number-summary`.
4. Implement the smallest useful change.
5. Update docs, roadmap, milestone, or TODO files when the decision or state changes.
6. Review the relevant self-review checklist in `docs/review/`.
7. Run the narrowest meaningful verification available.
8. Commit with Conventional Commits and include the Issue number.
9. Push the branch and create a PR linked to the Issue.
10. Merge after review and checks.
11. Return local `main` to the merged, clean state.

## Branch Names

Use Conventional Commit style as the prefix:

- `docs/1-governance-foundation`
- `feat/5-bank-csv-import`
- `fix/12-allocation-overpayment-credit`
- `test/8-dunning-minimum-interval`

## PR Requirements

Every PR should include:

- purpose
- change summary
- verification results
- self-review checklist used, when applicable
- related Issue, preferably `Closes #number`
- remaining risks or follow-up work

Do not commit directly to `main`.

## Closing Issues on Merge

GitHub only auto-closes a linked Issue when the PR is **merged**. If a PR is brought
onto `main` by **rebase** (the PR ends up `closed`, not `merged`), the `Closes #NN`
auto-close never fires and the Issue is silently left open. This has already caused a
backlog of "forgot to close" Issues.

To prevent it:

- Put a closing keyword (`Closes #NN` / `Fixes #NN` / `Resolves #NN`) in the **squash/merge
  commit body**, not only in the PR description. Closing keywords in the commit message
  reach `main` regardless of how the PR is integrated.
- The `Close issues on merge` workflow (`.github/workflows/close-issues.yml`) scans every
  commit pushed to `main` for these keywords and closes the referenced Issue. This is the
  safety net for rebase-merged work.
- Note: the conventional `(#NN)` reference in the subject is **not** a closing keyword and
  will not close anything — it only links the commit to the Issue. Always add an explicit
  closing keyword when the work fully resolves an Issue.

## Local Project Memory

- `docs/roadmap.md`: long-lived direction and phases
- `docs/milestones/`: medium-sized goals and acceptance criteria
- `docs/todo/current.md`: current task board and handoff notes
- `docs/adr/`: major architecture decisions
- `docs/inheritance-from-nene2.md`: NENE2 governance inheritance map

Do not leave important decisions only in chat. If it changes how the project should be built, record it in `docs/`.

Use ADRs for decisions that affect architecture, public contracts, dependency choices, or long-term maintenance. See `docs/development/adr.md`.

Use self-review checklists before push or PR. See `docs/development/self-review.md`.

## AI Agent Responsibilities

AI agents should manage the normal lifecycle when asked to complete work:

- create or reuse the Issue
- create the Issue branch
- read `AGENTS.md` and relevant docs before editing
- edit only relevant files
- review relevant self-review checklists
- verify the change
- commit, push, open PR, merge, and sync `main`
- update local docs that describe project state

If a user explicitly says investigation only, no commit, no PR, or another narrower scope, follow that instruction.

## Initial Issues Backlog

Phase 0 bootstrap Issues are tracked in `docs/todo/current.md`. After governance lands, use GitHub Issues for all work — no direct `main` commits.
