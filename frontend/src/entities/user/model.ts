// Domain model for a user. Mirrors the API JSON exactly (snake_case; no
// renaming) — see docs/development/fsd-a2-entities.md §4.1 (transitional: the
// A1 codemod later introduces the camelCase model + mapper, and a `session`
// slice for auth once it moves login/getCurrentUser).

export interface User {
  user_id: number
  organization_id: number | null
  email: string
  role: 'superadmin' | 'admin' | 'member' | 'viewer'
  status: 'active' | 'invited'
  /** Org fiscal year-end month (1–12), surfaced on /me. Null when unset. */
  fiscal_year_end_month?: number | null
}
