# Backend API Self-Review

Use for handlers, use cases, validation, routes, and HTTP behavior.

Source policies: `docs/development/backend-standards.md`, `docs/development/naming-conventions.md`, `docs/inheritance-from-nene2.md`.

## Checklist

- [ ] Change has a linked GitHub Issue; commit subject includes `(#issue)`.
- [ ] Handler stays thin: parse → DTO → UseCase → response.
- [ ] Business rules live in UseCase, not Handler or Repository.
- [ ] Money fields use integer cents — no floats.
- [ ] Japan invoice validation rules enforced in UseCase before persistence/PDF.
- [ ] All identifiers (fields, status values, `operationId`, slugs) match `docs/explanation/terminology.md` exactly — no typos, no unregistered terms.
- [ ] New routes documented in OpenAPI with `operationId`.
- [ ] Problem Details used for errors; no stack traces in responses.
- [ ] Admin mutating routes require auth when applicable.
- [ ] Tests cover happy path and at least one failure case.
- [ ] `composer check` or narrowest equivalent run and noted in PR.
