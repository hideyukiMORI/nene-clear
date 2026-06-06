import type { ProblemDetails } from '@/types'

const STORAGE_KEY = 'nene_clear_token'

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly problem: ProblemDetails,
  ) {
    super(problem.title)
    this.name = 'ApiError'
  }
}

function getToken(): string | null {
  return sessionStorage.getItem(STORAGE_KEY)
}

// Reactive auth store: the token lives in sessionStorage (so it survives a
// reload), but mutations also notify subscribers. The auth shell (RequireAuth)
// subscribes via useSyncExternalStore, so storing a token reveals the app and
// clearing it (logout or an expired 401) shows the login screen in place —
// without a hard navigation that would blank the page and lose the route.
const authListeners = new Set<() => void>()

function notifyAuthChange(): void {
  for (const listener of authListeners) listener()
}

/** Subscribe to token changes (for useSyncExternalStore in the auth shell). */
export function subscribeAuthChange(listener: () => void): () => void {
  authListeners.add(listener)
  return () => {
    authListeners.delete(listener)
  }
}

export function storeToken(token: string): void {
  sessionStorage.setItem(STORAGE_KEY, token)
  notifyAuthChange()
}

export function clearToken(): void {
  sessionStorage.removeItem(STORAGE_KEY)
  notifyAuthChange()
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
  const token = getToken()
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
  const token = getToken()
  if (token === null) return null

  const claims = decodeJwtPayload(token)
  return claims !== null && typeof claims.role === 'string' ? claims.role : null
}

/** Admin-tier roles (admin or the cross-tenant superadmin). */
export function isAdmin(): boolean {
  const role = getUserRole()
  return role === 'admin' || role === 'superadmin'
}

async function request<T>(
  method: string,
  path: string,
  body?: unknown,
  signal?: AbortSignal,
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  const token = getToken()
  if (token) headers['Authorization'] = `Bearer ${token}`

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  const res = await fetch(path, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
    signal,
  })

  // A 401 on a normal call means the session expired → clear the token. The auth
  // shell reacts to that and shows the login screen in place, at the current URL,
  // so re-logging in returns the user to the same screen. The login endpoint is
  // excluded: its 401 is "wrong credentials" and must surface to the form.
  if (res.status === 401 && !path.includes('/auth/login')) {
    clearToken()
    throw new ApiError(401, { type: 'unauthorized', title: 'Unauthorized', status: 401 })
  }

  if (!res.ok) {
    let problem: ProblemDetails
    try {
      problem = (await res.json()) as ProblemDetails
    } catch {
      problem = { type: 'unknown', title: 'Unknown error', status: res.status }
    }
    throw new ApiError(res.status, problem)
  }

  if (res.status === 204) return undefined as T

  return res.json() as Promise<T>
}

export const api = {
  get<T>(path: string, signal?: AbortSignal): Promise<T> {
    return request<T>('GET', path, undefined, signal)
  },
  post<T>(path: string, body?: unknown): Promise<T> {
    return request<T>('POST', path, body)
  },
  put<T>(path: string, body?: unknown): Promise<T> {
    return request<T>('PUT', path, body)
  },
  delete(path: string): Promise<void> {
    return request<void>('DELETE', path)
  },
}
