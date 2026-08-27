import type { ReactNode } from 'react'
import { Badge } from '@hideyukimori/nene2-ui'
import type { BadgeProps } from '@hideyukimori/nene2-ui'
import { useTranslation } from '@/shared/i18n/use-translation'
import type { MessageKey } from '@/shared/i18n/messages'

/**
 * A status value's badge tone + localized label key.
 *
 * 🔴 The tone vocabulary is the kit's, not Clear's. Until W1b this file said
 * `ok` / `bad` / `neut`; the kit's `Badge` bans those spellings by name —
 * "clear and invoice say `ok`, origin says `warning`; the contract bans both
 * synonyms" — so that a tone means the same thing in a badge as it does in an
 * alert. Clear was already inconsistent with itself: its Button said `danger`
 * while its Badge said `bad`.
 */
export interface StatusMeta {
  v: NonNullable<BadgeProps['tone']>
  labelKey: MessageKey
}

interface StatusBadgeProps<K extends string> {
  /** Maps each status value to its badge tone + label key. */
  map: Partial<Record<K, StatusMeta>>
  value: K
  dot?: boolean
  /** Shown when `value` is not in `map` (defaults to the raw value). */
  fallback?: ReactNode
}

/**
 * Renders a localized status/role <Badge> from a status→meta map. Replaces the
 * repeated `const m = MAP[value]; <Badge tone={m.v} dot>{t(m.labelKey)}</Badge>`
 * pattern across the list pages with one shared component.
 */
export function StatusBadge<K extends string>({ map, value, dot = false, fallback }: StatusBadgeProps<K>) {
  const { t } = useTranslation()
  const meta = map[value]
  return <Badge tone={meta?.v ?? 'neutral'} dot={dot}>{meta ? t(meta.labelKey) : (fallback ?? value)}</Badge>
}
