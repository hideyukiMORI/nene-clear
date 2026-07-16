// DEVIATION (transitional, tracked in #358): the FSD standard (01:229) forbids
// formatters in shared/lib and makes `@hideyukimori/nene2-i18n/format` canonical.
// That sub-path is not shipped yet (nene2-i18n 0.1.0 exports only "."), so there
// is no home to move to. Per the 2026-07-17 hub ruling these live here until the
// W0b `/format` sub-path ships, then move and this note is removed (#358).

/** Integer cents → JPY display string. Money is integer cents everywhere. */
export function yen(cents: number): string {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

/** ISO timestamp → date only (YYYY-MM-DD). */
export function formatDate(iso: string): string {
  return iso.slice(0, 10)
}

/** ISO timestamp → date + time (YYYY-MM-DD HH:mm). */
export function formatDateTime(iso: string): string {
  return iso.slice(0, 16).replace('T', ' ')
}

/** Days elapsed since a due date; '—' when not yet due or no due date (nullable per the Invoice contract). */
export function daysOverdue(due: string | null): string {
  if (due === null || due === '') return '—'
  const days = Math.floor((Date.now() - new Date(due).getTime()) / 86400000)
  return days > 0 ? `${days}日` : '—'
}
