import { describe, it, expect, afterEach, vi } from 'vitest'
import { yen, formatDate, formatDateTime, daysOverdue } from './format'

describe('yen', () => {
  it('renders integer cents as a JPY string (cents ÷ 100, ja-JP separators)', () => {
    expect(yen(0)).toBe('¥0')
    expect(yen(100)).toBe('¥1')
    expect(yen(150000)).toBe('¥1,500')
    expect(yen(250000)).toBe('¥2,500')
    expect(yen(11000000)).toBe('¥110,000')
    expect(yen(1234567800)).toBe('¥12,345,678')
  })

  it('floors sub-yen cents rather than rounding', () => {
    // 250 cents = ¥2.50 → floored to ¥2 (documents Math.floor, not round).
    expect(yen(250)).toBe('¥2')
    expect(yen(199)).toBe('¥1')
  })
})

describe('formatDate', () => {
  it('keeps only the YYYY-MM-DD date, for space- and T-separated timestamps', () => {
    expect(formatDate('2026-04-01 09:00:00')).toBe('2026-04-01')
    expect(formatDate('2026-04-01T09:00:00Z')).toBe('2026-04-01')
  })
})

describe('formatDateTime', () => {
  it('renders YYYY-MM-DD HH:mm, normalizing a T separator to a space', () => {
    expect(formatDateTime('2026-04-01T09:30:00')).toBe('2026-04-01 09:30')
    expect(formatDateTime('2026-04-01 09:30:45')).toBe('2026-04-01 09:30')
  })
})

describe('daysOverdue', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns the em dash when there is no due date', () => {
    expect(daysOverdue(null)).toBe('—')
    expect(daysOverdue('')).toBe('—')
  })

  it('counts whole days past a due date, and shows the em dash before/at due', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-04-10T00:00:00Z'))
    expect(daysOverdue('2026-04-05')).toBe('5日') // 5 days elapsed
    expect(daysOverdue('2026-04-10')).toBe('—')   // due today → not yet overdue
    expect(daysOverdue('2026-04-15')).toBe('—')   // future due date
  })
})
