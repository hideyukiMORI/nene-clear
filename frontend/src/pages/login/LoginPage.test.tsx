import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderWithProviders as render } from '@tests/render'

const loginMock = vi.fn()
const storeTokenMock = vi.fn()

vi.mock('@/shared/api/endpoints', () => ({
  login: (email: string, password: string) => loginMock(email, password),
}))

vi.mock('@/shared/api/client', () => ({
  storeToken: (token: string) => storeTokenMock(token),
}))

import LoginPage from './LoginPage'

function fillAndSubmit(email: string, password: string) {
  const emailInput = document.querySelector('input[name="email"]') as HTMLInputElement
  const passwordInput = document.querySelector('input[name="password"]') as HTMLInputElement
  fireEvent.input(emailInput, { target: { value: email } })
  fireEvent.input(passwordInput, { target: { value: password } })
  fireEvent.click(screen.getByRole('button'))
}

describe('LoginPage', () => {
  beforeEach(() => {
    loginMock.mockReset()
    storeTokenMock.mockReset()
  })

  it('renders the login form', () => {
    render(<LoginPage />)
    expect(screen.getByRole('button')).toBeInTheDocument()
    expect(document.querySelector('input[name="email"]')).toBeInTheDocument()
    expect(document.querySelector('input[name="password"]')).toBeInTheDocument()
  })

  it('stores the token on success (the auth shell reveals the route in place)', async () => {
    loginMock.mockResolvedValue({ token: 'jwt-success' })
    render(<LoginPage />)

    fillAndSubmit('admin@acme.example', 'secret-pass')

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith('admin@acme.example', 'secret-pass')
      expect(storeTokenMock).toHaveBeenCalledWith('jwt-success')
    })
  })

  it('shows the server error detail on failure', async () => {
    loginMock.mockRejectedValue({
      problem: { detail: 'メールアドレスまたはパスワードが正しくありません。' },
      message: 'ApiError',
    })
    render(<LoginPage />)

    fillAndSubmit('admin@acme.example', 'wrong')

    await waitFor(() => {
      expect(
        screen.getByText('メールアドレスまたはパスワードが正しくありません。'),
      ).toBeInTheDocument()
    })
    expect(storeTokenMock).not.toHaveBeenCalled()
  })

  it('blocks submit and does not call the API when fields are empty', async () => {
    render(<LoginPage />)

    fireEvent.click(screen.getByRole('button'))

    // zod validation prevents the submit handler from calling login()
    await waitFor(() => {
      expect(loginMock).not.toHaveBeenCalled()
    })
  })
})
