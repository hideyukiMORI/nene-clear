import {
  createNene2Transport,
  createSessionTokenStore,
  isNene2ClientError,
  isValidationProblemDetails,
  type Nene2ClientError,
} from '@hideyukimori/nene2-client'
import type { ProblemDetails } from './http-types'

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly problem: ProblemDetails,
  ) {
    super(problem.title)
    this.name = 'ApiError'
  }
}

/**
 * Human-facing message for an error from the API. Prefers the field-level
 * validation reasons (`errors[]`) so the user sees *what* is wrong, then the
 * localized `detail` (domain errors carry a translated one), then the title.
 * Falls back to the raw Error message for non-API failures.
 */
export function describeApiError(error: unknown): string {
  if (error instanceof ApiError) {
    const fieldMessages = (error.problem.errors ?? []).map(e => e.message).filter(Boolean)
    if (fieldMessages.length > 0) return fieldMessages.join(' / ')
    return error.problem.detail || error.problem.title
  }
  return error instanceof Error ? error.message : String(error)
}

/**
 * Fleet-standard bearer token store (`@hideyukimori/nene2-client`,
 * `createSessionTokenStore`): sessionStorage, same key as before this
 * migration (`nene_clear_token`) so an in-flight session survives a deploy.
 * The transport below is handed this same instance, so there is exactly one
 * store — one source of truth for get/set/clear.
 */
export const tokenStore = createSessionTokenStore({ key: 'nene_clear_token' })

/** Subscribe to token changes (for useSyncExternalStore in the auth shell). */
export const subscribeAuthChange = tokenStore.subscribe

export function storeToken(token: string): void {
  tokenStore.setToken(token)
}

export function clearToken(): void {
  tokenStore.clearToken()
}

/**
 * Decodes a JWT payload (base64url). Returns null for tokens that are not a
 * three-part JWT (e.g. E2E stub tokens) or that fail to decode.
 */
function decodeJwtPayload(token: string): Record<string, unknown> | null {
  const parts = token.split('.')
  if (parts.length !== 3) return null

  try {
    let b64 = parts[1].replace(/-/g, '+').replace(/_/g, '/')
    b64 += '='.repeat((4 - (b64.length % 4)) % 4)
    return JSON.parse(atob(b64)) as Record<string, unknown>
  } catch {
    return null
  }
}

/**
 * True when there is a token AND, if it is a decodable JWT, it has not expired.
 * Non-JWT stub tokens (no `exp`) are treated as valid. Pure — safe to use as a
 * useSyncExternalStore snapshot (it never mutates state).
 */
export function isAuthenticated(): boolean {
  const token = tokenStore.getToken()
  if (token === null) return false

  const claims = decodeJwtPayload(token)
  const exp = claims !== null && typeof claims.exp === 'number' ? claims.exp : null

  return exp === null || exp * 1000 > Date.now()
}

/**
 * The current user's role, decoded from the stored JWT's `role` claim. This is
 * for UI gating only — the backend capability checks remain the source of truth.
 * Returns null when there is no token or it is not a decodable JWT (e.g. a test
 * stub token).
 */
export function getUserRole(): string | null {
  const token = tokenStore.getToken()
  if (token === null) return null

  const claims = decodeJwtPayload(token)
  return claims !== null && typeof claims.role === 'string' ? claims.role : null
}

/** Admin-tier roles (admin or the cross-tenant superadmin). */
export function isAdmin(): boolean {
  const role = getUserRole()
  return role === 'admin' || role === 'superadmin'
}

/**
 * Fleet-standard transport (`@hideyukimori/nene2-client`, issue #102): every
 * request mirrors the bearer token onto `Authorization` *and*
 * `X-Authorization` so shared-hosting proxies that strip the standard header
 * still authenticate (#265, #312) — structurally, not via a hand-maintained
 * helper that a new call site could forget. `api` below is a thin adapter
 * that keeps this product's existing surface (`get/post/put/delete`) verbatim
 * so call sites did not need to change; `upload`/`getBlob` replace the old
 * `apiFetch` multipart/binary escape hatch — the mirror is structural now, so
 * a separate escape hatch is no longer needed (see
 * nene2-js/docs-site/howto/migrate-product-client.md).
 *
 * A 401 on a request that carried a token clears the token store
 * automatically (default `clearTokenOnStatuses: [401]`); the auth shell
 * (`RequireAuth`) reacts via `subscribeAuthChange`/`isAuthenticated` and shows
 * the login screen in place — no hard redirect. The login endpoint's own 401
 * (wrong credentials) never carries a token, so it is left alone and surfaces
 * as a normal `ApiError` to the login form, same as before.
 */
const transport = createNene2Transport({
  baseUrl: '',
  tokenStore,
  // Look up `fetch` at call time (not bind it once at module load): tests
  // patch `globalThis.fetch` via `vi.stubGlobal`, which can run after this
  // module has already been imported (nene2-js #105).
  fetch: (input, init) => globalThis.fetch(input, init),
})

/** Maps the package's `Nene2ClientError` to this product's `ApiError` (unchanged shape for callers). */
function toApiError(error: Nene2ClientError): ApiError {
  const problem = error.problem
  if (problem === undefined) {
    return new ApiError(error.status, {
      type: 'unknown',
      title: 'Unknown error',
      status: error.status,
    })
  }

  const mapped: ProblemDetails = {
    type: problem.type,
    title: problem.title,
    status: problem.status,
  }
  if (problem.detail !== undefined) {
    mapped.detail = problem.detail
  }
  if (isValidationProblemDetails(problem)) {
    mapped.errors = problem.errors
  }
  return new ApiError(error.status, mapped)
}

async function unwrap<T>(promise: Promise<T>): Promise<T> {
  try {
    return await promise
  } catch (error) {
    if (isNene2ClientError(error)) {
      throw toApiError(error)
    }
    throw error
  }
}

export const api = {
  get<T>(path: string, signal?: AbortSignal): Promise<T> {
    return unwrap(transport.get<T>(path, signal !== undefined ? { signal } : {}))
  },
  post<T>(path: string, body?: unknown): Promise<T> {
    return unwrap(transport.post<T>(path, body))
  },
  put<T>(path: string, body?: unknown): Promise<T> {
    return unwrap(transport.put<T>(path, body))
  },
  delete(path: string): Promise<void> {
    return unwrap(transport.delete<void>(path))
  },
  /** multipart/form-data upload; `Content-Type` (with boundary) is left to the browser. */
  upload<T>(path: string, formData: FormData): Promise<T> {
    return unwrap(transport.upload<T>(path, formData))
  },
  /** Authenticated binary download (CSV export). */
  async getBlob(path: string): Promise<Blob> {
    const { blob } = await unwrap(transport.getBlob(path))
    return blob
  },
}
