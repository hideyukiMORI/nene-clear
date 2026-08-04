// Domain model for a payment reconciliation and its allocations.
//
// A2 F2: the shapes are the OpenAPI contract types (`api-types.ts`), not
// hand-written mirrors. Still snake_case; the camelCase model + mapper are a
// later step (fsd-a2-entities.md §4.1).
//
// 🔴 This slice is why F2 was worth doing. The hand-written `ReconciliationAllocation`
// had drifted in BOTH directions and nothing could tell (#424):
//
//   - it omitted `source` and `manual_receivable_id`, which the API returns and
//     the spec declares — so allocation code could not tell a manual receivable
//     from an upstream invoice;
//   - it declared `organization_id` and `payment_reconciliation_id`, which the
//     API never sends, as non-optional `number`. TypeScript promised a number and
//     runtime would have handed back `undefined`. Nothing read them yet, so the
//     bug was still dormant — the compiler was guaranteeing something false and
//     would have kept doing so until someone built a screen on it.
//
// Taking the generated types deletes both directions at once, which is the whole
// argument for making the contract load-bearing rather than mirrored by hand.

import type { ReconciliationAllocationDto, ReconciliationDto } from './api-types'

export type ReconciliationAllocation = ReconciliationAllocationDto

export type Reconciliation = ReconciliationDto
