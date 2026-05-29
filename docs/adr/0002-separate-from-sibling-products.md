# ADR 0002: Separate Product from Sibling NeNe Applications

## Status

accepted

## Context

NeNe Clear is a quote and invoice platform. Sibling products in the NeNe ecosystem each own a distinct domain:

- **NeNe Records** — CMS and typed entity platform
- **NeNe Corpus** — knowledge chat with citations
- **NeNe Concierge** — scenario-driven conversion chat

NeNe Clear may consume sibling HTTP APIs (product catalog from Records, leads from Concierge) but must not embed billing logic into those repositories or share their databases.

Alternatives considered:

1. **Billing module inside NeNe Records** — rejected; mixes CMS and financial failure domains; Concierge already plans HTTP integration to separate Shop/Booking products.
2. **Shared database** — rejected; couples schemas and bypasses API contracts.
3. **Independent product with HTTP clients** (chosen): NeNe Clear calls sibling APIs only.

## Decision

NeNe Clear is a **separate repository and deployable unit**:

- Dependency direction: `NeNe Clear → sibling API`. Never `Sibling → NeNe Clear` code inclusion.
- No shared PHP codebase beyond Composer dependency on NENE2.
- No invoice routes, PDF generation, or payment logic in sibling repos.
- Siblings expose documented HTTP APIs; NeNe Clear implements `Upstream/` HTTP clients when integration is needed.
- MCP tools map to NeNe Clear OpenAPI operations only — not direct access to sibling databases.

```
Admin UI / MCP
    ↓
NeNe Clear API (clients, quotes, invoices, payments)
    ↓
NeNe Clear database (owned here)
    ↓ optional HTTP
NeNe Records / NeNe Concierge / external APIs
```

Billing-owned data (clients, quotes, invoices, payments, PDF artifacts metadata) lives in **NeNe Clear database only**.

## Consequences

**Benefits**

- Sibling products remain stable when billing services change.
- Clear OSS story: four products, one framework, HTTP integration.
- Security boundaries: CMS admin JWT ≠ billing admin JWT.

**Costs**

- Multiple repos to maintain; cross-repo API contracts must stay documented.
- Some duplication of admin UI patterns (acceptable; different domains).

**Follow-up**

- Document upstream client env vars in `docs/integrations/sibling-products.md`.
- Add contract tests when Records product catalog API is consumed.

## Related

- Product vision: `docs/explanation/product-vision.md`
- Sibling integration policy: `docs/integrations/sibling-products.md`
- NeNe Concierge glossary (Shop/Booking precedent): https://github.com/hideyukiMORI/nene-concierge/blob/main/docs/explanation/glossary.md
