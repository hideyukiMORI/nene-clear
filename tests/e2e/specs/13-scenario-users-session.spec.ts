import { test, expect } from '@playwright/test'
import {
  apiRoute, json, problem, list, loginViaForm, navTo, logout, user,
} from '../fixtures/helpers'

/**
 * Scenario 3 — User admin, modal edge cases, session expiry, re-login.
 *
 * login → users → invite (ok) → invite duplicate (409 in modal) → cancel →
 * reopen (modal must be clean, no stale error) → delete a user → session
 * expires mid-session (401) → login shown in place → re-login → back on the
 * same screen.
 *
 * Bug surface: modal state leakage between opens, cache invalidation on
 * invite/delete, and recovery from an expired session.
 */
test('scenario: user admin → 409 → modal reset → delete → 401 expiry → re-login', async ({ page }) => {
  let users = [
    user({ user_id: 1, email: 'admin@acme.example', role: 'admin', status: 'active' }),
    user({ user_id: 2, email: 'old@acme.example', role: 'viewer', status: 'active' }),
  ]
  let inviteAttempts = 0
  let sessionValid = true

  await apiRoute(page, '**/admin/bank-transactions/unmatched**', (route) =>
    json(route, 200, { items: [], total: 0, limit: 1, offset: 0 }),
  )
  await apiRoute(page, '**/admin/dunning-notices**', (route) => json(route, 200, list([])))

  await apiRoute(page, '**/admin/users?**', (route) => {
    if (!sessionValid) return problem(route, 401, 'unauthorized', 'Unauthorized')
    return json(route, 200, list(users))
  })
  await apiRoute(page, '**/admin/users', (route) => {
    if (route.request().method() === 'POST') {
      inviteAttempts += 1
      if (inviteAttempts === 1) {
        users = [...users, user({ user_id: 3, email: 'new@acme.example', role: 'member', status: 'invited' })]
        return json(route, 201, users[users.length - 1])
      }
      // Second invite collides on email.
      return problem(route, 409, 'user-already-exists', 'そのメールアドレスはすでに使用されています。')
    }
    return json(route, 200, list(users))
  })
  await apiRoute(page, '**/admin/users/*', (route) => {
    users = users.filter((u) => u.user_id !== 2)
    return json(route, 204, null)
  })

  // ── 1. Login → users ────────────────────────────────────────────────────
  await loginViaForm(page, { role: 'admin' })
  await navTo(page, 'ユーザー', /\/admin\/users/)
  await expect(page.getByText('old@acme.example')).toBeVisible()

  // ── 2. Invite a user (success) → list grows ─────────────────────────────
  await page.getByRole('button', { name: 'ユーザーを招待' }).click()
  await page.getByRole('dialog').getByRole('textbox').fill('new@acme.example')
  await page.getByRole('dialog').getByRole('button', { name: '招待を送信' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('new@acme.example')).toBeVisible()

  // ── 3. Invite again → 409 shown inside the modal ────────────────────────
  await page.getByRole('button', { name: 'ユーザーを招待' }).click()
  await page.getByRole('dialog').getByRole('textbox').fill('dup@acme.example')
  await page.getByRole('dialog').getByRole('button', { name: '招待を送信' }).click()
  await expect(page.getByText('そのメールアドレスはすでに使用されています。')).toBeVisible()

  // ── 4. Cancel, then reopen — the modal must be clean (no stale error) ────
  await page.getByRole('dialog').getByRole('button', { name: 'キャンセル' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await page.getByRole('button', { name: 'ユーザーを招待' }).click()
  await expect(page.getByText('そのメールアドレスはすでに使用されています。')).toHaveCount(0)
  await expect(page.getByRole('dialog').getByRole('textbox')).toHaveValue('')
  await page.getByRole('dialog').getByRole('button', { name: 'キャンセル' }).click()

  // ── 5. Delete a user → confirm → list shrinks ───────────────────────────
  await page
    .getByRole('row', { name: /old@acme.example/ })
    .getByRole('button', { name: '削除' })
    .click()
  await expect(page.getByText('このユーザーを削除しますか？')).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: '削除' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('old@acme.example')).toHaveCount(0)

  // ── 6. Session expires → next data load shows login in place (same URL) ──
  sessionValid = false
  await page.reload()
  // Still on /admin/users, but the cleared token flips the shell to the login form.
  await expect(page).toHaveURL(/\/admin\/users/)
  await expect(page.locator('input[name="email"]')).toBeVisible()

  // ── 7. Re-login returns to the same screen (the users page) ──────────────
  sessionValid = true
  await page.locator('input[name="email"]').fill('admin@nene-clear.dev')
  await page.locator('input[name="password"]').fill('admin1234')
  await page.getByRole('button').click()
  await expect(page).toHaveURL(/\/admin\/users/)
  await expect(page.getByText('admin@acme.example')).toBeVisible()
})
