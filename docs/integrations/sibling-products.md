# Sibling Product Integration

NeNe Clear integrates with other NeNe ecosystem products **via HTTP only**. See ADR 0002.

## Dependency direction

```
NeNe Clear  →  HTTP  →  NeNe Records / NeNe Concierge / NeNe Corpus (optional)
```

Never embed NeNe Clear code in sibling repositories. Never share databases.

## Planned integrations

| Sibling | Direction | Use case | Phase |
| --- | --- | --- | --- |
| **NeNe Records** | Invoice → Records (read) | Import product names and prices for line items | Phase 4+ |
| **NeNe Concierge** | Concierge → Invoice (write via webhook/API) | Create draft client or quote from scenario lead capture | Phase 4+ |
| **NeNe Corpus** | None required | No default integration | — |

## Environment variables (planned)

Document in `.env.example` when clients land:

| Variable | Purpose |
| --- | --- |
| `NENE_RECORDS_API_BASE_URL` | Optional product catalog upstream |
| `NENE_RECORDS_BEARER_TOKEN` | Read-only token for Records API |
| `NENE_CONCIERGE_WEBHOOK_SECRET` | Verify inbound lead webhooks |

## Implementation rules

- HTTP clients live in `src/Upstream/`.
- UseCases depend on interfaces, not concrete HTTP clients.
- Upstream failures must degrade gracefully (local manual entry always available).
- Contract tests when upstream OpenAPI is stable.

## Reporting bugs

| Symptom | Open Issue in |
| --- | --- |
| Missing Records catalog API | nene-records |
| Concierge action node needs Invoice endpoint | nene-concierge (or here if Invoice API is the deliverable) |
| NENE2 middleware / Problem Details | NENE2 |
