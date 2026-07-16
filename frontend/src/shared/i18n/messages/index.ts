import { ja, type MessageKey } from './ja'
import { en } from './en'

export type Locale = 'ja' | 'en'

export const DEFAULT_LOCALE: Locale = 'ja'

// Both catalogs are full Record<MessageKey, string> — a missing key in either
// language is a compile error, so language switching can never fall back to a
// raw key. `ja` is the authoritative catalog used for the (impossible) fallback.
const catalogs: Record<Locale, Record<MessageKey, string>> = { ja, en }

/** Narrow an arbitrary stored/navigator string to a supported locale. */
export function resolveLocale(value: string | null): Locale {
  return value === 'en' ? 'en' : 'ja'
}

/**
 * Pure translation: resolve the active catalog, fall back to the authoritative
 * `ja` catalog for a missing key, then interpolate `{{name}}` placeholders.
 * Stateless — the active locale lives in the I18n context, not a module global.
 */
export function translate(
  locale: Locale,
  key: MessageKey,
  vars?: Record<string, string | number>,
): string {
  const msg = catalogs[locale]?.[key] ?? ja[key]
  if (!vars) return msg
  return msg.replace(/\{\{(\w+)\}\}/g, (_match, name: string) => {
    const value = vars[name]
    return value === undefined ? `{{${name}}}` : String(value)
  })
}

export type { MessageKey }
