# Middleware and Security Self-Review

Use for auth, CORS, logging, rate limits, and security-sensitive changes.

Source policies: NENE2 middleware docs, `docs/integrations/ai-tools.md`.

## Checklist

- [ ] Secrets not logged (JWT, SMTP password, Invoice upstream bearer token).
- [ ] Invoice upstream credentials read from `.env` only; never returned in API responses or logs.
- [ ] CORS config explicit — no `*` in production paths.
- [ ] Auth middleware on admin mutating routes; tenant isolation (`organization_id`) enforced on every query (ADR 0006).
- [ ] CSV export endpoints scoped to the caller's organization and capability.
- [ ] No sensitive data (client PII, bank lines) in Problem Details `detail` for production.
- [ ] MCP write tools (`confirmMatch`, `applyClientCredit`, `sendDunningNotice`) reviewed for auth and audit requirements.
