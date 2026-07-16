function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function toIso(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

/** Today as a local YYYY-MM-DD string. */
export function todayIso(today: Date = new Date()): string {
  return toIso(today)
}

/**
 * Start date (YYYY-MM-DD) of the current fiscal year, given the fiscal year-end
 * month (決算月, 1–12). The fiscal year starts on the 1st of the month *after*
 * the end month; the current one is the most recent such 1st on or before today.
 *
 * e.g. end month 3 (March) → starts Apr 1; end month 12 → starts Jan 1.
 */
export function fiscalYearStart(fiscalYearEndMonth: number, today: Date = new Date()): string {
  const startMonth = (fiscalYearEndMonth % 12) + 1 // 1–12, month after the end month
  const candidate = new Date(today.getFullYear(), startMonth - 1, 1)
  const start = candidate.getTime() <= today.getTime()
    ? candidate
    : new Date(today.getFullYear() - 1, startMonth - 1, 1)
  return toIso(start)
}
