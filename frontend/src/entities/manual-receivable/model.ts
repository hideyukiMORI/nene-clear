// Domain models for manual receivables.
//
// A2 F2: the shape is the OpenAPI contract type (`api-types.ts`), not a
// hand-written mirror. Still snake_case; the camelCase model + mapper are a
// later step (fsd-a2-entities.md §4.1). What changes is *where the truth lives*:
// a spec change now breaks the build instead of silently disagreeing with the
// server.

import type { ManualReceivableDto, ManualReceivableImportResultDto } from './api-types'

/**
 * A receivable entered directly in Clear, not sourced from NeNe Invoice
 * (ADR 0014). A reconciliation reference — NOT an issued invoice / 適格請求書 /
 * tax original. `*_cents` are integer cents (yen × 100), like everywhere else.
 */
export type ManualReceivable = ManualReceivableDto

export type ManualReceivableImportResult = ManualReceivableImportResultDto
