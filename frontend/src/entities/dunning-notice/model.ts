// Domain model for a dunning notice.
//
// A2 F2: the shape is the OpenAPI contract type (`api-types.ts`), not a
// hand-written mirror. Still snake_case; the camelCase model + mapper are a
// later step (fsd-a2-entities.md §4.1). What changes is *where the truth lives*:
// a spec change now breaks the build instead of silently disagreeing with the
// server.

import type { DunningNoticeDto } from './api-types'

export type DunningNotice = DunningNoticeDto
