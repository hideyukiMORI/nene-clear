import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { I18nProvider } from './i18n-context'
import { useTranslation } from '@/shared/i18n/use-translation'

function Probe() {
  const { t, locale, setLocale } = useTranslation()
  return (
    <div>
      <span data-testid="label">{t('nav.dashboard')}</span>
      <span data-testid="locale">{locale}</span>
      <button onClick={() => setLocale('en')}>to-en</button>
      <button onClick={() => setLocale('ja')}>to-ja</button>
    </div>
  )
}

function renderProbe() {
  return render(
    <I18nProvider>
      <Probe />
    </I18nProvider>,
  )
}

describe('I18nProvider', () => {
  it('defaults to Japanese and sets <html lang>', () => {
    renderProbe()
    expect(screen.getByTestId('locale').textContent).toBe('ja')
    expect(screen.getByTestId('label').textContent).toBe('ダッシュボード')
    expect(document.documentElement.lang).toBe('ja')
  })

  it('switches language reactively and re-renders consumers', () => {
    renderProbe()
    fireEvent.click(screen.getByText('to-en'))
    expect(screen.getByTestId('locale').textContent).toBe('en')
    expect(screen.getByTestId('label').textContent).toBe('Dashboard')
    expect(document.documentElement.lang).toBe('en')
  })

  it('persists the chosen locale to localStorage', () => {
    renderProbe()
    fireEvent.click(screen.getByText('to-en'))
    expect(localStorage.getItem('locale')).toBe('en')
  })

  it('reads the persisted locale on mount', () => {
    localStorage.setItem('locale', 'en')
    renderProbe()
    expect(screen.getByTestId('locale').textContent).toBe('en')
  })

  it('throws when useTranslation is used outside the provider', () => {
    // Silence React's error boundary console noise for this expected throw.
    expect(() => render(<Probe />)).toThrow(/I18nProvider/)
  })
})
