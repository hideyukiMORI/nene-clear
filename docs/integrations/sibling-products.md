# Sibling Product Integration

NeNe Clear integrates with other NeNe ecosystem products **via HTTP only**.
See ADR 0002 and [ADR 0009](../adr/0009-separate-from-nene-invoice.md).

## Dependency direction

```
NeNe Clear  →  HTTP  →  NeNe Invoice (required upstream)
NeNe Clear  →  HTTP  →  NeNe Records / NeNe Concierge (optional, Phase 4+)
```

Never embed NeNe Clear in sibling repositories. Never share databases.

## NeNe Invoice — required upstream

**NeNe Invoice owns billing documents.** Clear owns reconciliation and dunning.
These are **separate products** — not upper compatible, not a migration path.

| Operation | Direction | Use case | Phase |
| --- | --- | --- | --- |
| **NeNe Invoice** | Clear → Invoice (read) | List open/overdue invoices, outstanding amounts | 1 |
| **NeNe Invoice** | Clear → Invoice (write) | Create/update payment after match confirmed | 1 |
| **NeNe Invoice** | Clear → Invoice (read) | Client names for match hints | 1 |

Clear **must not** duplicate invoice issuance, PDF generation, or tax calculation.

### Environment variables (planned)

| Variable | Purpose |
| --- | --- |
| `NENE_INVOICE_API_BASE_URL` | Invoice upstream API base URL |
| `NENE_INVOICE_BEARER_TOKEN` | Service token for Invoice API |

Document in `.env.example` when client lands.

## Optional siblings

| Sibling | Direction | Use case | Phase |
| --- | --- | --- | --- |
| **NeNe Records** | Clear → Records (read) | Enrich dunning context with client catalog metadata | 4+ |
| **NeNe Concierge** | — | No default integration | — |
| **NeNe Corpus** | — | No default integration | — |

## Implementation rules

- Invoice client lives in `src/Upstream/Invoice/`.
- UseCases depend on interfaces, not concrete HTTP clients.
- Invoice upstream failure: degraded mode (import-only) with operator warning.
- Contract tests against Invoice OpenAPI when stable.

## Reporting bugs

| Symptom | Open Issue in |
| --- | --- |
| Invoice API missing payment endpoint Clear needs | **nene-invoice** |
| Records metadata for dunning | nene-records |
| NENE2 middleware / Problem Details | NENE2 |

## Related

- ADR 0009: Domain split
- [`product-vision.md`](../explanation/product-vision.md)
