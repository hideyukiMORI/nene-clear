import type { Page, Route } from '@playwright/test'

/**
 * Admin SPA E2E helpers.
 *
 * Auth: the SPA stores a bare JWT string in sessionStorage under
 * `nene_clear_token`. bypassLogin() injects it before page scripts run.
 *
 * API mocking: page routes and API endpoints share the `/admin` prefix
 * (e.g. /admin/users is both a page route and an API endpoint). apiRoute()
 * only fulfills fetch/xhr requests; document navigations fall through to the
 * preview server's SPA fallback.
 */

export const TOKEN_KEY = 'nene_clear_token'
export const E2E_TOKEN = 'e2e-test-token'

export async function bypassLogin(page: Page): Promise<void> {
  await page.addInitScript(
    ([key, token]) => {
      sessionStorage.setItem(key, token)
    },
    [TOKEN_KEY, E2E_TOKEN],
  )
}

/**
 * Log in through the real form. Unlike bypassLogin this does NOT re-inject the
 * token on every navigation, so logout / session-expiry flows behave for real.
 * The login endpoint must be mocked by the caller's apiRoute setup, or this
 * registers a default success.
 */
export async function loginViaForm(
  page: Page,
  opts: { role?: string; orgId?: number | null; email?: string } = {},
): Promise<void> {
  const email = opts.email ?? 'admin@nene-clear.dev'
  await apiRoute(page, '**/admin/auth/login', (route) =>
    json(route, 200, {
      token: 'e2e-jwt',
      user: { user_id: 1, email, role: opts.role ?? 'admin', organization_id: opts.orgId ?? 7 },
    }),
  )
  await page.goto('/login')
  await page.locator('input[name="email"]').fill(email)
  await page.locator('input[name="password"]').fill('admin1234')
  await page.getByRole('button').click()
  await page.waitForURL(/\/admin$/)
}

/** Click a sidebar nav link by its visible label and wait for the route. */
export async function navTo(page: Page, label: string, urlRe: RegExp): Promise<void> {
  await page.getByRole('link', { name: label }).click()
  await page.waitForURL(urlRe)
}

/** Click the logout button and confirm we land on /login. */
export async function logout(page: Page, label = 'ログアウト'): Promise<void> {
  await page.getByRole('button', { name: label }).click()
  await page.waitForURL(/\/login/)
}

/**
 * Register an API route that only handles fetch/xhr requests. Document
 * navigations (resourceType 'document') are continued so the SPA loads.
 */
export async function apiRoute(
  page: Page,
  glob: string,
  handler: (route: Route) => Promise<void> | void,
): Promise<void> {
  await page.route(glob, async (route) => {
    const rt = route.request().resourceType()
    if (rt !== 'fetch' && rt !== 'xhr') {
      return route.continue()
    }
    return handler(route)
  })
}

export function json(route: Route, status: number, body: unknown): Promise<void> {
  return route.fulfill({
    status,
    contentType: status === 204 ? 'text/plain' : 'application/json',
    body: status === 204 ? '' : JSON.stringify(body),
  })
}

export function problem(
  route: Route,
  status: number,
  type: string,
  title: string,
  detail?: string,
  extra: Record<string, unknown> = {},
): Promise<void> {
  return route.fulfill({
    status,
    contentType: 'application/problem+json',
    body: JSON.stringify({ type, title, status, detail, ...extra }),
  })
}

/** Standard list envelope. */
export function list<T>(items: T[], total?: number, limit = 50, offset = 0) {
  return { items, total: total ?? items.length, limit, offset }
}

// ── Domain fixture builders ─────────────────────────────────────────────────

export function bankTransaction(over: Record<string, unknown> = {}) {
  return {
    bank_transaction_id: 1,
    organization_id: 7,
    bank_import_batch_id: 1,
    bank_account_id: 1,
    value_date: '2026-04-20',
    amount_cents: 110000,
    counterparty_text: 'カ）アクメ',
    status: 'unmatched',
    ...over,
  }
}

export function bankImportBatch(over: Record<string, unknown> = {}) {
  return {
    bank_import_batch_id: 1,
    organization_id: 7,
    bank_account_id: 1,
    file_hash: 'abc',
    source_filename: 'april.csv',
    row_count: 12,
    status: 'imported',
    imported_at: '2026-04-21 09:00:00',
    imported_by: 1,
    reversed_at: null,
    reversal_reason: null,
    ...over,
  }
}

export function reconciliation(over: Record<string, unknown> = {}) {
  return {
    payment_reconciliation_id: 1,
    organization_id: 7,
    bank_transaction_id: 1,
    status: 'confirmed',
    reason_code: null,
    confirmed_by: 1,
    confirmed_at: '2026-04-21 10:00:00',
    reversed_at: null,
    reversal_reason: null,
    allocations: [],
    ...over,
  }
}

export function dunningNotice(over: Record<string, unknown> = {}) {
  return {
    dunning_notice_id: 1,
    organization_id: 7,
    invoice_id: 123,
    invoice_number: 'INV-2026-001',
    client_id: 45,
    recipient_email: 'accounts@acme.example',
    outstanding_at_send_cents: 110000,
    due_at: '2026-04-30',
    channel: 'log',
    sent_by: 1,
    sent_at: '2026-04-25 09:00:00',
    ...over,
  }
}

export function user(over: Record<string, unknown> = {}) {
  return {
    user_id: 1,
    organization_id: 7,
    email: 'member@acme.example',
    role: 'member',
    status: 'active',
    ...over,
  }
}

export function clearSettings(over: Record<string, unknown> = {}) {
  return {
    organization_id: 7,
    dunning_min_interval_days: 7,
    bank_accounts: [
      {
        bank_account_id: 1,
        bank_name: 'テスト銀行',
        bank_branch: '本店',
        account_type: 'ordinary',
        account_number: '1234567',
      },
    ],
    ...over,
  }
}
