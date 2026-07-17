import type { ReactNode } from 'react'
import { Badge } from '@/shared/ui/badge'
import type { BadgeVariant } from '@/shared/ui/badge'
import { useTranslation } from '@/shared/i18n/use-translation'
import type { MessageKey } from '@/shared/i18n/messages'

/** A status value's badge tone + localized label key. */
export interface StatusMeta {
  v: BadgeVariant
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
 * repeated `const m = MAP[value]; <Badge variant={m.v} dot>{t(m.labelKey)}</Badge>`
 * pattern across the list pages with one shared component.
 */
export function StatusBadge<K extends string>({ map, value, dot = false, fallback }: StatusBadgeProps<K>) {
  const { t } = useTranslation()
  const meta = map[value]
  return <Badge variant={meta?.v ?? 'neut'} dot={dot}>{meta ? t(meta.labelKey) : (fallback ?? value)}</Badge>
}
