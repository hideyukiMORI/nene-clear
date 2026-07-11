import { useContext } from 'react'
import { I18nContext, type I18nContextValue } from '@/contexts/I18nContext'

/**
 * Access the active locale, the `t()` translator, and `setLocale`. Must be used
 * under an <I18nProvider>; throwing here surfaces a missing provider at the
 * first render rather than silently rendering the default language.
 */
export function useTranslation(): I18nContextValue {
  const ctx = useContext(I18nContext)
  if (ctx === null) {
    throw new Error('useTranslation must be used within an I18nProvider')
  }
  return ctx
}
