# AI Tools Policy

NeNe Clear inherits NENE2 AI integration principles with reconciliation/dunning boundaries (human confirms, AI proposes).

## Agent entry

- `AGENTS.md` — read first
- `CLAUDE.md` — quick rules
- `.cursor/rules/` — Cursor summaries

## MCP boundary

- MCP tools map to **OpenAPI HTTP operations** only.
- MCP is for **operators and development agents** — not direct database or shell access.
- Do not add tools that read the database directly or execute shell commands without Issue + security review.
- Match suggestions may be AI-proposed, but **human confirmation is required** before a match is final (compliance §2.8).
- Write tools (`confirmMatch`, `applyClientCredit`, `sendDunningNotice`) require auth + audit review before catalog publication.

Validate catalog:

```bash
composer mcp
```

## Secrets in agent sessions

Agents must not commit:

- `.env` files
- Admin JWT secrets
- Production upstream URLs with embedded credentials
- SMTP passwords

## Cross-repo work

- CMS or catalog API gaps → Issue in **nene-records**, not workarounds here.
- Lead capture API gaps → Issue in **nene-concierge**.
- Framework bugs → Issue in **NENE2**.

See also: `docs/integrations/sibling-products.md`, ADR 0002.
