import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { api, ApiError, storeToken, clearToken, isAuthenticated } from './client'

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function emptyResponse(status: number): Response {
  return new Response(null, { status })
}

/** A non-JSON error body (e.g. an upstream 5xx served by a proxy, not PHP). */
function nonJsonResponse(status: number): Response {
  return new Response('Internal Server Error', {
    status,
    headers: { 'Content-Type': 'text/plain' },
  })
}

describe('token storage', () => {
  it('stores, reads, and clears the token', () => {
    clearToken()
    expect(isAuthenticated()).toBe(false)
    storeToken('jwt-abc')
    expect(isAuthenticated()).toBe(true)
    clearToken()
    expect(isAuthenticated()).toBe(false)
  })
})

describe('api request (nene2-client transport adapter)', () => {
  let fetchMock: ReturnType<typeof vi.fn>

  beforeEach(() => {
    clearToken()
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('GET sends Accept header and parses JSON', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ hello: 'world' }))

    const result = await api.get<{ hello: string }>('/admin/x')

    expect(result).toEqual({ hello: 'world' })
    const [path, init] = fetchMock.mock.calls[0]
    expect(path).toBe('/admin/x')
    expect(init.method).toBe('GET')
    expect(init.headers.get('Accept')).toBe('application/json')
    expect(init.body).toBeUndefined()
  })

  it('omits Authorization when no token', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}))

    await api.get('/admin/x')

    const init = fetchMock.mock.calls[0][1]
    expect(init.headers.get('Authorization')).toBeNull()
  })

  it('POST serializes body and sets Content-Type', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ id: 1 }, 201))

    await api.post('/admin/x', { email: 'a@b.c' })

    const init = fetchMock.mock.calls[0][1]
    expect(init.method).toBe('POST')
    expect(init.headers.get('Content-Type')).toBe('application/json')
    expect(init.body).toBe(JSON.stringify({ email: 'a@b.c' }))
  })

  it('returns undefined for 204 No Content', async () => {
    fetchMock.mockResolvedValue(emptyResponse(204))

    const result = await api.delete('/admin/x/1')

    expect(result).toBeUndefined()
  })

  it('throws ApiError with parsed problem details on 4xx', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ type: 'validation-failed', title: 'Validation Failed', status: 422, detail: 'bad input' }, 422),
    )

    await expect(api.post('/admin/x', {})).rejects.toMatchObject({
      status: 422,
      problem: { type: 'validation-failed', detail: 'bad input' },
    })
  })

  it('falls back to a generic problem when the body is not JSON', async () => {
    fetchMock.mockResolvedValue(nonJsonResponse(500))

    try {
      await api.get('/admin/x')
      expect.unreachable('should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError)
      const e = err as ApiError
      expect(e.status).toBe(500)
      expect(e.problem.title).toBe('Unknown error')
    }
  })
})

/**
 * The bearer token must ride on both `Authorization` and its `X-Authorization`
 * mirror on every verb (#265): some shared-hosting front proxies strip the
 * standard header before it reaches PHP. Mirrors the fleet transport-adopt
 * wave-1 exemplar (nene-payout #155) — the mirror is now structural
 * (`@hideyukimori/nene2-client`), not a hand-maintained helper a call site
 * could forget.
 */
describe('auth header mirror on every verb (#265, #312)', () => {
  let fetchMock: ReturnType<typeof vi.fn>
  const TOKEN = 'jwt-xyz'

  beforeEach(() => {
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    storeToken(TOKEN)
  })

  afterEach(() => {
    clearToken()
    vi.unstubAllGlobals()
  })

  function sentHeaders(callIndex = 0): Headers {
    return fetchMock.mock.calls[callIndex][1].headers as Headers
  }

  it('mirrors both headers on GET', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}))
    await api.get('/admin/x')
    expect(sentHeaders().get('Authorization')).toBe(`Bearer ${TOKEN}`)
    expect(sentHeaders().get('X-Authorization')).toBe(`Bearer ${TOKEN}`)
  })

  it('mirrors both headers on POST', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}))
    await api.post('/admin/x', { a: 1 })
    expect(sentHeaders().get('Authorization')).toBe(`Bearer ${TOKEN}`)
    expect(sentHeaders().get('X-Authorization')).toBe(`Bearer ${TOKEN}`)
  })

  it('mirrors both headers on PUT', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}))
    await api.put('/admin/x', { a: 1 })
    expect(sentHeaders().get('Authorization')).toBe(`Bearer ${TOKEN}`)
    expect(sentHeaders().get('X-Authorization')).toBe(`Bearer ${TOKEN}`)
  })

  it('mirrors both headers on DELETE', async () => {
    fetchMock.mockResolvedValue(emptyResponse(204))
    await api.delete('/admin/x/1')
    expect(sentHeaders().get('Authorization')).toBe(`Bearer ${TOKEN}`)
    expect(sentHeaders().get('X-Authorization')).toBe(`Bearer ${TOKEN}`)
  })

  it('mirrors both headers on upload (multipart)', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ row_count: 3 }))
    await api.upload('/admin/bank-import-batches', new FormData())
    expect(sentHeaders().get('Authorization')).toBe(`Bearer ${TOKEN}`)
    expect(sentHeaders().get('X-Authorization')).toBe(`Bearer ${TOKEN}`)
    // Content-Type is left to the browser so it can add the multipart boundary.
    expect(sentHeaders().has('Content-Type')).toBe(false)
  })

  it('mirrors both headers on getBlob (CSV export)', async () => {
    fetchMock.mockResolvedValue(
      new Response(new Blob(['a,b\n1,2']), { status: 200, headers: { 'Content-Type': 'text/csv' } }),
    )
    await api.getBlob('/admin/export/bank-transactions')
    expect(sentHeaders().get('Authorization')).toBe(`Bearer ${TOKEN}`)
    expect(sentHeaders().get('X-Authorization')).toBe(`Bearer ${TOKEN}`)
  })
})

describe('401 handling', () => {
  let fetchMock: ReturnType<typeof vi.fn>
  const realLocation = window.location

  beforeEach(() => {
    clearToken()
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    Object.defineProperty(window, 'location', {
      value: { href: '' },
      writable: true,
      configurable: true,
    })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    Object.defineProperty(window, 'location', {
      value: realLocation,
      writable: true,
      configurable: true,
    })
  })

  it('clears the token on 401 (auth shell shows login in place — no hard redirect)', async () => {
    storeToken('expired-jwt')
    fetchMock.mockResolvedValue(emptyResponse(401))

    await expect(api.get('/admin/x')).rejects.toBeInstanceOf(ApiError)

    expect(isAuthenticated()).toBe(false)
    // The token is cleared but the URL is left intact, so re-login returns the
    // user to the same screen. No `onUnauthorized`/`onForbidden` redirect is
    // configured — the auth shell is purely reactive (RequireAuth/router.tsx).
    expect(window.location.href).toBe('')
  })

  it('does NOT clear or redirect on 401 from the login endpoint (no token attached)', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({ type: 'invalid-credentials', title: '認証失敗', status: 401, detail: 'wrong' }, 401),
    )

    // The login 401 never carries a token (the user has no session yet), so
    // the transport's default clearTokenOnStatuses/onUnauthorized never fire;
    // it surfaces as a normal ApiError so the form can show it.
    await expect(api.post('/admin/auth/login', {})).rejects.toMatchObject({
      status: 401,
      problem: { type: 'invalid-credentials' },
    })
    expect(window.location.href).toBe('')
  })
})
