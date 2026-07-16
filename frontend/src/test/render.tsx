import { render, type RenderOptions } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { I18nProvider } from '@/shared/i18n/i18n-context'

function Providers({ children }: { children: ReactNode }) {
  return <I18nProvider>{children}</I18nProvider>
}

/**
 * render() with the app-wide providers every screen assumes at runtime (the
 * I18n provider, so `useTranslation` resolves). Compose additional providers
 * (QueryClient, Router) around the passed UI as each test needs them.
 */
export function renderWithProviders(ui: ReactElement, options?: Omit<RenderOptions, 'wrapper'>) {
  return render(ui, { wrapper: Providers, ...options })
}
