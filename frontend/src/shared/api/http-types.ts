// Transport-level wire envelopes shared across every domain. Not a domain
// entity — these describe the HTTP contract itself (RFC 9457 Problem Details
// and the list-response wrapper), so they live in shared/api, not entities/*.

export interface ProblemDetails {
  type: string
  title: string
  status: number
  detail?: string
  errors?: Array<{ field: string; code: string; message: string }>
}

export interface ListEnvelope<T> {
  items: T[]
  limit: number
  offset: number
  total: number
}
