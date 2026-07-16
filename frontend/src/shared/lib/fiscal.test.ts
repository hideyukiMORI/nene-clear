import { describe, it, expect } from 'vitest'
import { fiscalYearStart, todayIso } from './fiscal'

describe('fiscalYearStart', () => {
  it('March year-end (3) → fiscal year starts April 1; current year when past April', () => {
    expect(fiscalYearStart(3, new Date(2026, 5, 8))).toBe('2026-04-01') // June 8 → FY started Apr 1
  })

  it('March year-end (3) → previous April when before April', () => {
    expect(fiscalYearStart(3, new Date(2026, 1, 15))).toBe('2025-04-01') // Feb → still FY2025
  })

  it('boundary: on April 1 itself uses the current year', () => {
    expect(fiscalYearStart(3, new Date(2026, 3, 1))).toBe('2026-04-01')
  })

  it('December year-end (12) → calendar year (Jan 1)', () => {
    expect(fiscalYearStart(12, new Date(2026, 5, 8))).toBe('2026-01-01')
  })

  it('January year-end (1) → starts Feb 1', () => {
    expect(fiscalYearStart(1, new Date(2026, 5, 8))).toBe('2026-02-01')
    expect(fiscalYearStart(1, new Date(2026, 0, 15))).toBe('2025-02-01')
  })

  it('todayIso formats local date', () => {
    expect(todayIso(new Date(2026, 5, 8))).toBe('2026-06-08')
  })
})
