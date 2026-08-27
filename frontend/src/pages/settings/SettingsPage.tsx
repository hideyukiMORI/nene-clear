import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getClearSettings, updateClearSettings, testUpstreamConnection } from '@/shared/api/endpoints'
import type { BankAccountDraft, ClearSettings } from '@/entities/clear-settings'
import { Button } from '@hideyukimori/nene2-ui'
import { Card } from '@hideyukimori/nene2-ui'
import { CardFoot } from '@/shared/ui/card'
import { Icon } from '@/shared/ui/icon'
import { Notice } from '@/shared/ui/notice'
import { PageHead } from '@/shared/ui/page-head'
import { useTranslation } from '@/shared/i18n/use-translation'

function emptyAccount(): BankAccountDraft {
  return { bank_name: '', bank_branch: '', account_type: 'ordinary', account_number: '', csv_encoding: 'utf8', csv_date_format: 'Y/m/d', csv_date_column: 0, csv_amount_column: 1, csv_counterparty_column: 3, csv_header_rows: 1 }
}

function SettingsForm({ settings }: { settings: ClearSettings }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [upstreamUrl, setUpstreamUrl] = useState(settings.upstream_base_url)
  const [upstreamToken, setUpstreamToken] = useState(settings.upstream_token_ref)
  const [dunningInterval, setDunningInterval] = useState(settings.dunning_min_interval_days)
  const [fiscalMonth, setFiscalMonth] = useState<number | null>(settings.fiscal_year_end_month ?? null)
  const [accounts, setAccounts] = useState<BankAccountDraft[]>(settings.bank_accounts)
  // Account numbers are masked by default; revealing one is an explicit per-row
  // action so the number isn't shown in plaintext on screen-share / over a
  // shoulder (#192). True server-side withholding is tracked separately.
  const [revealedAccounts, setRevealedAccounts] = useState<Set<number>>(new Set())
  const toggleReveal = (i: number) => setRevealedAccounts(prev => {
    const next = new Set(prev)
    if (next.has(i)) { next.delete(i) } else { next.add(i) }
    return next
  })
  const [testResult, setTestResult] = useState<'ok' | 'fail' | null>(null)
  const [testing, setTesting] = useState(false)
  const [saved, setSaved] = useState(false)
  const [intervalError, setIntervalError] = useState('')

  const saveMut = useMutation({
    // The schedule fields are echoed back exactly as loaded. This screen does not
    // edit them yet, but PUT is a FULL REPLACE (#284) — omitting them would reset
    // scheduled dunning to off on every unrelated save, with no error and no clue.
    mutationFn: () => updateClearSettings({
      upstream_base_url: upstreamUrl,
      upstream_token_ref: upstreamToken,
      dunning_min_interval_days: dunningInterval,
      fiscal_year_end_month: fiscalMonth,
      bank_accounts: accounts,
      is_dunning_schedule_enabled: settings.is_dunning_schedule_enabled,
      dunning_initial_after_days: settings.dunning_initial_after_days,
      dunning_reminder_after_days: settings.dunning_reminder_after_days,
      dunning_final_after_days: settings.dunning_final_after_days,
      dunning_window_start_hour: settings.dunning_window_start_hour,
      dunning_window_end_hour: settings.dunning_window_end_hour,
      is_dunning_weekdays_only: settings.is_dunning_weekdays_only,
      dunning_max_per_run: settings.dunning_max_per_run,
    } as Partial<ClearSettings>),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['clear-settings'] })
      // The org's fiscal year-end month is held in the /me cache (it drives the
      // fiscal-year date-range defaults); refresh it after a settings change.
      void qc.invalidateQueries({ queryKey: ['auth', 'me'] })
      setSaved(true); setTimeout(() => setSaved(false), 3000)
    },
  })

  // The dunning interval must be at least one day. Guard client-side so an
  // invalid value never reaches the API (mirrors the upstream constraint).
  function handleSave() {
    if (!Number.isInteger(dunningInterval) || dunningInterval < 1) {
      setIntervalError(t('settings.intervalError'))
      return
    }
    setIntervalError('')
    saveMut.mutate()
  }

  async function handleTest() {
    setTestResult(null); setTesting(true)
    try { const r = await testUpstreamConnection(); setTestResult(r.ok ? 'ok' : 'fail') }
    catch { setTestResult('fail') }
    finally { setTesting(false) }
  }

  function updateAccount(i: number, field: keyof BankAccountDraft, v: string | number) {
    setAccounts(p => p.map((a, idx) => idx === i ? { ...a, [field]: v } : a))
  }

  return (
    <Card pad="none" className="min-w-0" style={{ maxWidth: 780 }}>
      {/* Section: API */}
      <div className="set-sect">
        <h3><Icon decorative name="plug" />{t('settings.section.api')}</h3>
        <div className="form-grid" style={{ gridTemplateColumns: '1fr' }}>
          <div className="field"><label>{t('settings.upstreamUrl')}</label><input className="inp" type="url" value={upstreamUrl} onChange={e => setUpstreamUrl(e.target.value)} /></div>
          <div className="field"><label>{t('settings.upstreamToken')}</label><input className="inp mono" type="password" value={upstreamToken} onChange={e => setUpstreamToken(e.target.value)} /></div>
          <div className="row">
            <Button variant="outline" tone="neutral" onClick={handleTest} disabled={testing}><Icon decorative name="refresh" />{testing ? t('common.checking') : t('settings.testConnection')}</Button>
            {testResult === 'ok' && <span className="row" style={{ color: 'var(--ok)', fontWeight: 600, fontSize: '12.5px', gap: 6 }}><Icon decorative name="check" size="sm" />{t('settings.connectionOk')}</span>}
            {testResult === 'fail' && <span className="row" style={{ color: 'var(--bad)', fontWeight: 600, fontSize: '12.5px', gap: 6 }}><Icon decorative name="x" size="sm" />{t('settings.connectionFail')}</span>}
          </div>
        </div>
      </div>

      {/* Section: Dunning */}
      <div className="set-sect">
        <h3><Icon decorative name="bell" />{t('settings.section.dunning')}</h3>
        <div className="form-row">
          <div className="field">
            <label>{t('settings.dunningInterval')}</label>
            <input className="inp tnum" type="number" min={1} data-testid="dunning-interval" value={dunningInterval} style={{ width: 120 }} onChange={e => setDunningInterval(Number(e.target.value))} />
            {intervalError && <span className="field-error" style={{ color: 'var(--bad)', fontSize: 12 }}>{intervalError}</span>}
          </div>
        </div>
      </div>

      {/* Section: Fiscal year */}
      <div className="set-sect">
        <h3><Icon decorative name="calendar" />{t('settings.section.fiscal')}</h3>
        <div className="form-row">
          <div className="field">
            <label>{t('settings.fiscalYearEndMonth')}</label>
            <select
              className="inp"
              data-testid="fiscal-year-end-month"
              style={{ width: 160 }}
              value={fiscalMonth ?? ''}
              onChange={e => setFiscalMonth(e.target.value === '' ? null : Number(e.target.value))}
            >
              <option value="">{t('settings.fiscalYearEndMonthUnset')}</option>
              {Array.from({ length: 12 }, (_, i) => i + 1).map(m => (
                <option key={m} value={m}>{t('settings.monthLabel', { n: m })}</option>
              ))}
            </select>
            <span className="faint" style={{ fontSize: 12 }}>{t('settings.fiscalYearEndMonthHint')}</span>
          </div>
        </div>
      </div>

      {/* Section: Bank accounts */}
      <div className="set-sect">
        <h3 className="spread" style={{ display: 'flex' }}>
          <span className="row" style={{ gap: 8 }}><Icon decorative name="building" />{t('settings.section.banks')}</span>
          <Button variant="outline" tone="neutral" size="sm" onClick={() => setAccounts(p => [...p, emptyAccount()])}>
            <Icon decorative name="plus" size="sm" />{t('settings.addBankAccount')}
          </Button>
        </h3>
        {accounts.length === 0 && <p className="faint" style={{ fontSize: 12 }}>{t('settings.noBankAccounts')}</p>}
        {accounts.map((acc, i) => (
          <div key={i} className="acct-card">
            <div className="form-row" style={{ alignItems: 'stretch' }}>
              <div className="field" style={{ flex: 1, minWidth: 160 }}><label>{t('settings.bankName')}</label><input className="inp" value={acc.bank_name} onChange={e => updateAccount(i, 'bank_name', e.target.value)} /></div>
              <div className="field" style={{ flex: 1, minWidth: 160 }}><label>{t('settings.bankBranch')}</label><input className="inp" value={acc.bank_branch} onChange={e => updateAccount(i, 'bank_branch', e.target.value)} /></div>
              <div className="field" style={{ width: 110 }}>
                <label>{t('settings.accountType')}</label>
                <select className="inp" value={acc.account_type} onChange={e => updateAccount(i, 'account_type', e.target.value)}>
                  <option value="ordinary">{t('settings.accountType.ordinary')}</option>
                  <option value="current">{t('settings.accountType.current')}</option>
                </select>
              </div>
              <div className="field" style={{ flex: 1, minWidth: 140 }}>
                <label>{t('settings.accountNumber')}</label>
                <div style={{ display: 'flex', gap: 6 }}>
                  <input className="inp mono" style={{ flex: 1, minWidth: 0 }} type={revealedAccounts.has(i) ? 'text' : 'password'} value={acc.account_number} onChange={e => updateAccount(i, 'account_number', e.target.value)} />
                  <Button variant="outline" tone="neutral" size="sm" onClick={() => toggleReveal(i)}>{t(revealedAccounts.has(i) ? 'settings.hideNumber' : 'settings.revealNumber')}</Button>
                </div>
              </div>
            </div>
            <details style={{ marginTop: 12 }}>
              <summary style={{ cursor: 'pointer', fontSize: 12, color: 'var(--navy-500)', fontWeight: 600 }}>{t('settings.csvMapping')}</summary>
              <div className="form-grid" style={{ gridTemplateColumns: 'repeat(4,1fr)', marginTop: 12 }}>
                <div className="field"><label style={{ fontSize: 11 }}>{t('settings.csv.dateCol')}</label><input className="inp tnum" type="number" value={acc.csv_date_column ?? 0} onChange={e => updateAccount(i, 'csv_date_column', Number(e.target.value))} /></div>
                <div className="field"><label style={{ fontSize: 11 }}>{t('settings.csv.amountCol')}</label><input className="inp tnum" type="number" value={acc.csv_amount_column ?? 1} onChange={e => updateAccount(i, 'csv_amount_column', Number(e.target.value))} /></div>
                <div className="field"><label style={{ fontSize: 11 }}>{t('settings.csv.counterpartyCol')}</label><input className="inp tnum" type="number" value={acc.csv_counterparty_column ?? 3} onChange={e => updateAccount(i, 'csv_counterparty_column', Number(e.target.value))} /></div>
                <div className="field"><label style={{ fontSize: 11 }}>{t('settings.csv.headerRows')}</label><input className="inp tnum" type="number" value={acc.csv_header_rows ?? 1} onChange={e => updateAccount(i, 'csv_header_rows', Number(e.target.value))} /></div>
              </div>
            </details>
            <div className="spread" style={{ marginTop: 12 }}>
              <span />
              <Button variant="link" style={{ color: 'var(--bad)' }} onClick={() => setAccounts(p => p.filter((_, j) => j !== i))}>
                <Icon decorative name="trash" size="sm" />{t('settings.removeBankAccount')}
              </Button>
            </div>
          </div>
        ))}
      </div>

      <CardFoot>
        {saved
          ? <div className="notice ok" style={{ border: 0, background: 'transparent', padding: 0 }}><Icon decorative name="check" size="sm" /><span>{t('settings.saved')}</span></div>
          : <span />
        }
        {saveMut.isError && <Notice variant="bad">{saveMut.error.message}</Notice>}
        <Button disabled={saveMut.isPending} onClick={handleSave}>
          <Icon decorative name="check" />{saveMut.isPending ? t('common.saving') : t('settings.save')}
        </Button>
      </CardFoot>
    </Card>
  )
}

export default function SettingsPage() {
  const { t } = useTranslation()
  const settingsQ = useQuery({ queryKey: ['clear-settings'], queryFn: ({ signal }) => getClearSettings(signal) })

  return (
    <>
      <PageHead title={t('settings.title')} sub={t('settings.subtitle')} />
      {settingsQ.isLoading && <p className="muted">{t('common.loading')}</p>}
      {settingsQ.isError && <Notice variant="bad">{settingsQ.error.message}</Notice>}
      {settingsQ.data && <SettingsForm settings={settingsQ.data} />}
    </>
  )
}
