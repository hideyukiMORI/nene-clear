import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { getClearSettings, updateClearSettings, testUpstreamConnection } from '@/api/endpoints'
import type { BankAccount, ClearSettings } from '@/types'
import { Icon, Button, Card, CardFoot, Notice, PageHead } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'

function emptyAccount(): BankAccount {
  return { bank_name: '', bank_branch: '', account_type: 'ordinary', account_number: '', csv_encoding: 'utf8', csv_date_format: 'Y/m/d', csv_date_column: 0, csv_amount_column: 1, csv_counterparty_column: 3, csv_header_rows: 1 }
}

function SettingsForm({ settings }: { settings: ClearSettings }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [upstreamUrl, setUpstreamUrl] = useState(settings.upstream_base_url)
  const [upstreamToken, setUpstreamToken] = useState(settings.upstream_token_ref)
  const [dunningInterval, setDunningInterval] = useState(settings.dunning_min_interval_days)
  const [accounts, setAccounts] = useState<BankAccount[]>(settings.bank_accounts)
  const [testResult, setTestResult] = useState<'ok' | 'fail' | null>(null)
  const [testing, setTesting] = useState(false)
  const [saved, setSaved] = useState(false)

  const saveMut = useMutation({
    mutationFn: () => updateClearSettings({ upstream_base_url: upstreamUrl, upstream_token_ref: upstreamToken, dunning_min_interval_days: dunningInterval, bank_accounts: accounts } as Partial<ClearSettings>),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['clear-settings'] }); setSaved(true); setTimeout(() => setSaved(false), 3000) },
  })

  async function handleTest() {
    setTestResult(null); setTesting(true)
    try { const r = await testUpstreamConnection(); setTestResult(r.ok ? 'ok' : 'fail') }
    catch { setTestResult('fail') }
    finally { setTesting(false) }
  }

  function updateAccount(i: number, field: keyof BankAccount, v: string | number) {
    setAccounts(p => p.map((a, idx) => idx === i ? { ...a, [field]: v } : a))
  }

  return (
    <Card style={{ maxWidth: 780 }}>
      {/* Section: API */}
      <div className="set-sect">
        <h3><Icon name="plug" />{t('settings.section.api')}</h3>
        <div className="form-grid" style={{ gridTemplateColumns: '1fr' }}>
          <div className="field"><label>{t('settings.upstreamUrl')}</label><input className="inp" type="url" value={upstreamUrl} onChange={e => setUpstreamUrl(e.target.value)} /></div>
          <div className="field"><label>{t('settings.upstreamToken')}</label><input className="inp mono" type="password" value={upstreamToken} onChange={e => setUpstreamToken(e.target.value)} /></div>
          <div className="row">
            <Button variant="ghost" onClick={handleTest} disabled={testing}><Icon name="refresh" />{testing ? t('common.checking') : t('settings.testConnection')}</Button>
            {testResult === 'ok' && <span className="row" style={{ color: 'var(--ok)', fontWeight: 600, fontSize: '12.5px', gap: 6 }}><Icon name="check" size="sm" />{t('settings.connectionOk')}</span>}
            {testResult === 'fail' && <span className="row" style={{ color: 'var(--bad)', fontWeight: 600, fontSize: '12.5px', gap: 6 }}><Icon name="x" size="sm" />{t('settings.connectionFail')}</span>}
          </div>
        </div>
      </div>

      {/* Section: Dunning */}
      <div className="set-sect">
        <h3><Icon name="bell" />{t('settings.section.dunning')}</h3>
        <div className="form-row">
          <div className="field">
            <label>{t('settings.dunningInterval')}</label>
            <input className="inp tnum" type="number" value={dunningInterval} style={{ width: 120 }} onChange={e => setDunningInterval(Number(e.target.value))} />
          </div>
        </div>
      </div>

      {/* Section: Bank accounts */}
      <div className="set-sect">
        <h3 className="spread" style={{ display: 'flex' }}>
          <span className="row" style={{ gap: 8 }}><Icon name="building" />{t('settings.section.banks')}</span>
          <Button variant="ghost" size="sm" onClick={() => setAccounts(p => [...p, emptyAccount()])}>
            <Icon name="plus" size="sm" />{t('settings.addBankAccount')}
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
              <div className="field" style={{ flex: 1, minWidth: 140 }}><label>{t('settings.accountNumber')}</label><input className="inp mono" value={acc.account_number} onChange={e => updateAccount(i, 'account_number', e.target.value)} /></div>
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
                <Icon name="trash" size="sm" />{t('settings.removeBankAccount')}
              </Button>
            </div>
          </div>
        ))}
      </div>

      <CardFoot>
        {saved
          ? <div className="notice ok" style={{ border: 0, background: 'transparent', padding: 0 }}><Icon name="check" size="sm" /><span>{t('settings.saved')}</span></div>
          : <span />
        }
        {saveMut.isError && <Notice variant="bad">{saveMut.error.message}</Notice>}
        <Button variant="primary" disabled={saveMut.isPending} onClick={() => saveMut.mutate()}>
          <Icon name="check" />{saveMut.isPending ? t('common.saving') : t('settings.save')}
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
