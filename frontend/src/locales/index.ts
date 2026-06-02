import { ja, type MessageKey } from './ja'
import { en } from './en'

export type Locale = 'ja' | 'en'

// Both catalogs are full Record<MessageKey, string> — a missing key in either
// language is a compile error, so language switching can never fall back.
const catalogs: Record<Locale, Record<MessageKey, string>> = { ja, en }

let currentLocale: Locale = (localStorage.getItem('locale') as Locale) ?? 'ja'

export function getLocale(): Locale {
  return currentLocale
}

export function setLocale(locale: Locale): void {
  currentLocale = locale
  localStorage.setItem('locale', locale)
  document.documentElement.lang = locale
}

export function t(key: MessageKey, vars?: Record<string, string | number>): string {
  const msg = catalogs[currentLocale]?.[key] ?? ja[key]
  if (!vars) return msg
  return Object.entries(vars).reduce(
    (s, [k, v]) => s.replace(`{{${k}}}`, String(v)),
    msg,
  )
}

export type { MessageKey }
