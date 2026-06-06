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

export function storeToken(token: string): void {
  sessionStorage.setItem(STORAGE_KEY, token)
}

export function clearToken(): void {
  sessionStorage.removeItem(STORAGE_KEY)
}

export function isAuthenticated(): boolean {
  return getToken() !== null
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

  const parts = token.split('.')
  if (parts.length !== 3) return null

  try {
    let b64 = parts[1].replace(/-/g, '+').replace(/_/g, '/')
    b64 += '='.repeat((4 - (b64.length % 4)) % 4)
    const claims = JSON.parse(atob(b64)) as { role?: unknown }
    return typeof claims.role === 'string' ? claims.role : null
  } catch {
    return null
  }
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

  // A 401 on a normal call means the session expired → clear and bounce to login.
  // The login endpoint itself is excluded: its 401 is "wrong credentials" and
  // must surface to the form, not trigger a redirect loop.
  if (res.status === 401 && !path.includes('/auth/login')) {
    clearToken()
    window.location.href = '/login'
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
