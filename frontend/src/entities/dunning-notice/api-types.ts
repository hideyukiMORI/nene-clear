import type { components } from '@/shared/api/schema.gen'

/**
 * Wire types for this entity, taken from the OpenAPI contract.
 *
 * Importing the generated schema is what makes the contract *load-bearing*: a
 * field the spec adds, removes or renames surfaces as a type error here instead
 * of as `undefined` at runtime. Before A2 F2 these slices declared their own
 * hand-written mirrors and `schema.gen.ts` — 2,900 generated, drift-checked
 * lines — had zero consumers (#409).
 */
export type DunningNoticeDto = components['schemas']['DunningNotice']
