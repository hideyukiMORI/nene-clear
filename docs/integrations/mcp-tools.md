# MCP Tools — Catalog & Policy

NeNe Clear exposes its operations to AI agents through an MCP tool catalog that
mirrors the OpenAPI contract. This document is the policy; the catalog is
[`../mcp/tools.json`](../mcp/tools.json).

Follows NENE2 MCP policy (`docs/integrations/mcp-tools.md`, `endpoint-scaffold.md`)
and Clear's [`nene2-compliance.md`](../development/nene2-compliance.md) §12.

> MCP is for **operators and development agents**, not public/end-client use
> ([`ai-tools.md`](./ai-tools.md)). Phase 4 on the roadmap; the catalog is
> designed now because the contract exists.

## Rules

1. **Tool name = OpenAPI `operationId`, exactly.** `title` is short English Title
   Case; `description` is safe and English. Each tool's `source` points to the
   `operationId`/method/path in [`../openapi/openapi.yaml`](../openapi/openapi.yaml)
   and a `responseSchemaRef`. Validate with `composer mcp`.
2. **Read-only first.** Only tools that read (no side effects) are in the catalog
   today — including `proposeMatch`, which is a POST but writes nothing.
3. **Mutation tools are gated** (NENE2 rule): they are **not** added to
   `tools.json` until authentication, capability checks, audit, request-id
   propagation, and human confirmation are implemented and documented.
4. **Human confirms, AI proposes** (compliance §2.8): an agent may call
   `proposeMatch` to suggest, but **finalizing** a match, reversing one, applying
   credit, importing, or sending dunning is a human-authorized action — never a
   silent agent side effect.
5. **No secrets in the catalog** (no tokens, keys, or `.env` values). The MCP
   server holds the operator credential/scope out of band.

## Read tools (in the catalog)

`getHealth`, `listUnmatchedTransactions`, `listBankTransactions`,
`getBankTransactionById`, `listBankImportBatches`,
`listUpstreamInvoices`, `proposeMatch`,
`listReconciliations`, `getReconciliationById`, `listClientCredits`.

All except `getHealth` require the configured operator bearer credential and the
`view_reconciliation` capability; tenant scoping (`organization_id`) applies.

## Gated write tools (NOT in the catalog yet)

| Planned tool | operationId | Required before catalog entry |
| --- | --- | --- |
| Confirm match | `confirmMatch` | auth + `manage_reconciliation` + audit + request id + **explicit human confirmation**; writes payment upstream |
| Reverse reconciliation | `reverseReconciliation` | same + reason recorded |
| Apply client credit | `applyClientCredit` | same; explicit operator action only |
| Import bank CSV | `importBankCsv` | auth + `manage_reconciliation` + audit; large-payload handling |
| Reverse import batch | `reverseBankImportBatch` | same + reason recorded |
| Send dunning notice | `sendDunningNotice` | **Phase 2**; auth + `send_dunning` + audit + min-interval + 弁護士-reviewed template (compliance §4) |

Each gated tool, when introduced, ships with its OpenAPI security requirements,
Problem Details responses, audit events, and a confirmation step — added in a
focused Issue, not by default.

## Validation

```bash
composer mcp        # validates tools.json against docs/openapi/openapi.yaml
```

Tool `operationId`, method, path, and `responseSchemaRef` must all resolve in the
OpenAPI document, and names must match `operationId` exactly (terminology §5).

## Related

- Catalog: [`../mcp/tools.json`](../mcp/tools.json)
- Contract: [`../openapi/openapi.yaml`](../openapi/openapi.yaml)
- NENE2 compliance: [`../development/nene2-compliance.md`](../development/nene2-compliance.md) §12
- AI tools policy: [`./ai-tools.md`](./ai-tools.md)
- Human-confirms rule: [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) §2.8
