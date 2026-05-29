# Middleware and Security Self-Review

Use for auth, CORS, logging, rate limits, and security-sensitive changes.

Source policies: NENE2 middleware docs, `docs/integrations/ai-tools.md`.

## Checklist

- [ ] Secrets not logged (JWT, SMTP password, upstream bearer tokens).
- [ ] Admin JWT not exposed to public document download routes.
- [ ] CORS config explicit — no `*` in production paths.
- [ ] Auth middleware on admin mutating routes.
- [ ] PDF download tokens time-limited or scoped when public.
- [ ] No sensitive data in Problem Details `detail` for production.
- [ ] MCP write tools reviewed for auth and audit requirements.
