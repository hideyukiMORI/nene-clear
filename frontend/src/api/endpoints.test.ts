import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  login,
  listBankTransactions,
  listUnmatchedTransactions,
  listReconciliations,
  confirmMatch,
  reverseReconciliation,
  sendDunningNotice,
} from './endpoints'

/**
 * These tests drive the endpoint functions through the real api client and a
 * mocked global fetch, asserting the request URL, query string, method, and
 * body shape. Field names must stay snake_case (terminology.md).
 */
describe('endpoint request construction', () => {
  let fetchMock: ReturnType<typeof vi.fn>

  beforeEach(() => {
    fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({}),
    } as unknown as Response)
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  function calledUrl(): string {
    return String(fetchMock.mock.calls[0][0])
  }

  function calledInit(): { method: string; body?: string; headers: Record<string, string> } {
    return fetchMock.mock.calls[0][1]
  }

  it('login posts email/password to the auth endpoint', async () => {
    await login('a@b.c', 'pw')
    expect(calledUrl()).toBe('/admin/auth/login')
    expect(calledInit().method).toBe('POST')
    expect(JSON.parse(calledInit().body!)).toEqual({ email: 'a@b.c', password: 'pw' })
  })

  it('listBankTransactions builds a filtered query string', async () => {
    await listBankTransactions({
      status: 'unmatched',
      value_date_from: '2026-04-01',
      counterparty: 'ACME',
      limit: 20,
      offset: 40,
    })
    const url = calledUrl()
    expect(url).toContain('/admin/bank-transactions?')
    expect(url).toContain('status=unmatched')
    expect(url).toContain('value_date_from=2026-04-01')
    expect(url).toContain('counterparty=ACME')
    expect(url).toContain('limit=20')
    expect(url).toContain('offset=40')
  })

  it('listBankTransactions omits unset filters but keeps pagination defaults', async () => {
    await listBankTransactions({})
    const url = calledUrl()
    expect(url).not.toContain('status=')
    expect(url).not.toContain('counterparty=')
    expect(url).toContain('limit=50')
    expect(url).toContain('offset=0')
  })

  it('listUnmatchedTransactions builds a filtered, sorted query string', async () => {
    await listUnmatchedTransactions({
      valueDateFrom: '2026-04-01',
      amountMaxCents: 100000,
      counterparty: 'ACME',
      sortBy: 'amount_cents',
      sortDir: 'asc',
      limit: 20,
      offset: 20,
    })
    const url = calledUrl()
    expect(url).toContain('/admin/bank-transactions/unmatched?')
    expect(url).toContain('value_date_from=2026-04-01')
    expect(url).toContain('amount_max_cents=100000')
    expect(url).toContain('counterparty=ACME')
    expect(url).toContain('sort_by=amount_cents')
    expect(url).toContain('sort_dir=asc')
    expect(url).toContain('limit=20')
    expect(url).toContain('offset=20')
    expect(url).not.toContain('status=')
  })

  it('listUnmatchedTransactions omits unset filters but keeps pagination defaults', async () => {
    await listUnmatchedTransactions({})
    const url = calledUrl()
    expect(url).not.toContain('counterparty=')
    expect(url).not.toContain('sort_by=')
    expect(url).toContain('limit=50')
    expect(url).toContain('offset=0')
  })

  it('listReconciliations includes status only when given', async () => {
    await listReconciliations({ status: 'confirmed' })
    expect(calledUrl()).toContain('status=confirmed')
  })

  it('confirmMatch posts snake_case allocation payload', async () => {
    await confirmMatch(99, [{ invoice_id: 1, amount_cents: 110000 }], 'fee_absorption')
    expect(calledUrl()).toBe('/admin/reconciliations')
    expect(JSON.parse(calledInit().body!)).toEqual({
      bank_transaction_id: 99,
      allocations: [{ invoice_id: 1, amount_cents: 110000 }],
      reason_code: 'fee_absorption',
    })
  })

  it('reverseReconciliation posts the reversal reason', async () => {
    await reverseReconciliation(7, 'operator error')
    expect(calledUrl()).toBe('/admin/reconciliations/7/reverse')
    expect(JSON.parse(calledInit().body!)).toEqual({ reversal_reason: 'operator error' })
  })

  it('sendDunningNotice posts the invoice id', async () => {
    await sendDunningNotice(123)
    expect(calledUrl()).toBe('/admin/dunning-notices')
    expect(JSON.parse(calledInit().body!)).toEqual({ invoice_id: 123 })
  })
})
