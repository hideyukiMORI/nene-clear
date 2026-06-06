import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { api, ApiError, storeToken, clearToken, isAuthenticated } from './client'

function mockResponse(opts: {
  ok: boolean
  status: number
  json?: unknown
  jsonThrows?: boolean
}): Response {
  return {
    ok: opts.ok,
    status: opts.status,
    json: opts.jsonThrows
      ? () => Promise.reject(new Error('not json'))
      : () => Promise.resolve(opts.json),
  } as unknown as Response
}

describe('token storage', () => {
  it('stores, reads, and clears the token', () => {
    expect(isAuthenticated()).toBe(false)
    storeToken('jwt-abc')
    expect(isAuthenticated()).toBe(true)
    clearToken()
    expect(isAuthenticated()).toBe(false)
  })
})

describe('api request', () => {
  let fetchMock: ReturnType<typeof vi.fn>

  beforeEach(() => {
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('GET sends Accept header and parses JSON', async () => {
    fetchMock.mockResolvedValue(mockResponse({ ok: true, status: 200, json: { hello: 'world' } }))

    const result = await api.get<{ hello: string }>('/admin/x')

    expect(result).toEqual({ hello: 'world' })
    const [path, init] = fetchMock.mock.calls[0]
    expect(path).toBe('/admin/x')
    expect(init.method).toBe('GET')
    expect(init.headers.Accept).toBe('application/json')
    expect(init.body).toBeUndefined()
  })

  it('injects the bearer token when present', async () => {
    storeToken('jwt-xyz')
    fetchMock.mockResolvedValue(mockResponse({ ok: true, status: 200, json: {} }))

    await api.get('/admin/x')

    const init = fetchMock.mock.calls[0][1]
    expect(init.headers.Authorization).toBe('Bearer jwt-xyz')
  })

  it('omits Authorization when no token', async () => {
    fetchMock.mockResolvedValue(mockResponse({ ok: true, status: 200, json: {} }))

    await api.get('/admin/x')

    const init = fetchMock.mock.calls[0][1]
    expect(init.headers.Authorization).toBeUndefined()
  })

  it('POST serializes body and sets Content-Type', async () => {
    fetchMock.mockResolvedValue(mockResponse({ ok: true, status: 201, json: { id: 1 } }))

    await api.post('/admin/x', { email: 'a@b.c' })

    const init = fetchMock.mock.calls[0][1]
    expect(init.method).toBe('POST')
    expect(init.headers['Content-Type']).toBe('application/json')
    expect(init.body).toBe(JSON.stringify({ email: 'a@b.c' }))
  })

  it('returns undefined for 204 No Content', async () => {
    fetchMock.mockResolvedValue(mockResponse({ ok: true, status: 204 }))

    const result = await api.delete('/admin/x/1')

    expect(result).toBeUndefined()
  })

  it('throws ApiError with parsed problem details on 4xx', async () => {
    fetchMock.mockResolvedValue(
      mockResponse({
        ok: false,
        status: 422,
        json: { type: 'validation-failed', title: 'Validation Failed', status: 422, detail: 'bad input' },
      }),
    )

    await expect(api.post('/admin/x', {})).rejects.toMatchObject({
      status: 422,
      problem: { type: 'validation-failed', detail: 'bad input' },
    })
  })

  it('falls back to a generic problem when body is not JSON', async () => {
    fetchMock.mockResolvedValue(mockResponse({ ok: false, status: 500, jsonThrows: true }))

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

describe('401 handling', () => {
  let fetchMock: ReturnType<typeof vi.fn>
  const realLocation = window.location

  beforeEach(() => {
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
    fetchMock.mockResolvedValue(mockResponse({ ok: false, status: 401 }))

    await expect(api.get('/admin/x')).rejects.toBeInstanceOf(ApiError)

    expect(isAuthenticated()).toBe(false)
    // The token is cleared but the URL is left intact, so re-login returns the
    // user to the same screen.
    expect(window.location.href).toBe('')
  })

  it('does NOT clear or redirect on 401 from the login endpoint', async () => {
    fetchMock.mockResolvedValue(
      mockResponse({
        ok: false,
        status: 401,
        json: { type: 'invalid-credentials', title: '認証失敗', status: 401, detail: 'wrong' },
      }),
    )

    // The login 401 must surface as a normal ApiError (so the form can show it),
    // not trigger the global redirect.
    await expect(api.post('/admin/auth/login', {})).rejects.toMatchObject({
      status: 401,
      problem: { type: 'invalid-credentials' },
    })
    expect(window.location.href).toBe('')
  })
})
