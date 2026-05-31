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

/** Days elapsed since a due date; returns `${n}日` or '—' when not yet due. */
export function daysOverdue(due: string): string {
  const days = Math.floor((Date.now() - new Date(due).getTime()) / 86400000)
  return days > 0 ? `${days}日` : '—'
}
