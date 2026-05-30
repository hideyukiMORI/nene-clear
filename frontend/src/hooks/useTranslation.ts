import { useCallback, useSyncExternalStore } from 'react'
import { t, getLocale, setLocale, type Locale, type MessageKey } from '@/locales'

const listeners = new Set<() => void>()

function subscribe(cb: () => void) {
  listeners.add(cb)
  return () => listeners.delete(cb)
}

export function switchLocale(locale: Locale) {
  setLocale(locale)
  listeners.forEach(l => l())
}

export function useTranslation() {
  useSyncExternalStore(subscribe, getLocale)
  const translate = useCallback(
    (key: MessageKey, vars?: Record<string, string | number>) => t(key, vars),
    [],
  )
  return { t: translate, locale: getLocale(), switchLocale }
}
