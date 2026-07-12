import { describe, it, expect } from 'vitest'
import { translate, resolveLocale, DEFAULT_LOCALE } from './index'

describe('translate (pure)', () => {
  it('returns Japanese strings for the ja locale', () => {
    expect(translate('ja', 'nav.dashboard')).toBe('ダッシュボード')
  })

  it('returns English strings for the en locale', () => {
    expect(translate('en', 'nav.dashboard')).toBe('Dashboard')
  })

  it('interpolates {{var}} placeholders', () => {
    expect(translate('ja', 'common.pagination.showing', { from: 1, to: 20, total: 53 })).toBe(
      '1〜20 件 / 53 件',
    )
  })

  it('interpolates the manual-receivable import result banner (regression #322)', () => {
    expect(translate('ja', 'manualReceivables.importResult', { created: 2, skipped: 1, errors: 0 })).toBe(
      '作成 2 件 / スキップ 1 件 / エラー 0 件',
    )
    expect(translate('en', 'manualReceivables.importResult', { created: 2, skipped: 1, errors: 0 })).toBe(
      'Created 2 / skipped 1 / errors 0',
    )
  })

  it('falls back to the authoritative ja catalog and never returns the raw key', () => {
    expect(translate('en', 'nav.dashboard')).not.toBe('nav.dashboard')
  })

  it('localizes the invitation_accepted audit event in both languages (#196)', () => {
    expect(translate('ja', 'audit.event.invitation_accepted')).toBe('招待受諾（アカウント有効化）')
    expect(translate('en', 'audit.event.invitation_accepted')).toBe('Invitation accepted')
  })

  it('carries a glossary annotation for each bookkeeping term (#196)', () => {
    for (const key of ['reconcile', 'match', 'clientCredit', 'allocate'] as const) {
      expect(translate('ja', `glossary.${key}.def`)).not.toBe('')
      expect(translate('en', `glossary.${key}.def`)).not.toBe('')
    }
  })
})

describe('resolveLocale', () => {
  it('narrows "en" to en and everything else to the default', () => {
    expect(resolveLocale('en')).toBe('en')
    expect(resolveLocale('ja')).toBe('ja')
    expect(resolveLocale('fr')).toBe(DEFAULT_LOCALE)
    expect(resolveLocale(null)).toBe(DEFAULT_LOCALE)
  })
})
