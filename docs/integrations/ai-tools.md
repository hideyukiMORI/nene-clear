# AI Tools Policy

NeNe Clear inherits NENE2 AI integration principles with billing-specific boundaries.

## Agent entry

- `AGENTS.md` — read first
- `CLAUDE.md` — quick rules
- `.cursor/rules/` — Cursor summaries

## MCP boundary

- MCP tools map to **OpenAPI HTTP operations** only.
- MCP is for **operators and development agents**, not public document download clients.
- Do not add tools that read the database directly or execute shell commands without Issue + security review.
- Write tools (create invoice, mark paid) require auth review before catalog publication.

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
