# OpenAPI Contract Self-Review

Use for OpenAPI schema changes, examples, and contract tests.

Source policies: `docs/development/naming-conventions.md`, NENE2 OpenAPI conventions.

## Checklist

- [ ] Every new route has `operationId` in camelCase — stable after release.
- [ ] Request/response schemas use snake_case property names.
- [ ] Money fields documented as integer cents with description.
- [ ] Success and Problem Details responses documented.
- [ ] Examples included for non-trivial endpoints.
- [ ] `composer openapi` passes.
- [ ] MCP catalog updated when ops-facing endpoints change (`composer mcp`).
- [ ] Public text (summary, description) is English.
