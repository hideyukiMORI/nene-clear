import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { getClearSettings, updateClearSettings, testUpstreamConnection } from '@/api/endpoints'
import type { BankAccount, ClearSettings } from '@/types'

// ------- Bank Account Form Row -------
interface BankAccountRowProps {
  account: BankAccount
  index: number
  onUpdate: (index: number, field: keyof BankAccount, value: string | number) => void
  onRemove: (index: number) => void
}

function BankAccountRow({ account, index, onUpdate, onRemove }: BankAccountRowProps) {
  const { t } = useTranslation()
  return (
    <div className="rounded border border-gray-200 p-4 space-y-3">
      <div className="flex gap-3">
        <div className="flex-1">
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('settings.bankName')}</label>
          <input
            type="text"
            value={account.bank_name}
            onChange={e => onUpdate(index, 'bank_name', e.target.value)}
            className="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none"
          />
        </div>
        <div className="flex-1">
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('settings.bankBranch')}</label>
          <input
            type="text"
            value={account.bank_branch}
            onChange={e => onUpdate(index, 'bank_branch', e.target.value)}
            className="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none"
          />
        </div>
      </div>
      <div className="flex gap-3">
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('settings.accountType')}</label>
          <select
            value={account.account_type}
            onChange={e => onUpdate(index, 'account_type', e.target.value)}
            className="rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none"
          >
            <option value="ordinary">{t('settings.accountType.ordinary')}</option>
            <option value="current">{t('settings.accountType.current')}</option>
          </select>
        </div>
        <div className="flex-1">
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('settings.accountNumber')}</label>
          <input
            type="text"
            value={account.account_number}
            onChange={e => onUpdate(index, 'account_number', e.target.value)}
            className="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none"
          />
        </div>
      </div>
      <div>
        <details className="text-xs text-gray-500">
          <summary className="cursor-pointer hover:text-gray-700">{t('settings.csvSettings')}</summary>
          <div className="mt-2 grid grid-cols-2 gap-2">
            <div>
              <label className="block text-xs mb-0.5">日付列 (0始まり)</label>
              <input
                type="number" min="0"
                value={account.csv_date_column ?? 0}
                onChange={e => onUpdate(index, 'csv_date_column', Number(e.target.value))}
                className="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-xs mb-0.5">金額列</label>
              <input
                type="number" min="0"
                value={account.csv_amount_column ?? 1}
                onChange={e => onUpdate(index, 'csv_amount_column', Number(e.target.value))}
                className="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-xs mb-0.5">振込人列</label>
              <input
                type="number" min="0"
                value={account.csv_counterparty_column ?? 2}
                onChange={e => onUpdate(index, 'csv_counterparty_column', Number(e.target.value))}
                className="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-xs mb-0.5">ヘッダー行数</label>
              <input
                type="number" min="0"
                value={account.csv_header_rows ?? 1}
                onChange={e => onUpdate(index, 'csv_header_rows', Number(e.target.value))}
                className="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-xs mb-0.5">日付フォーマット</label>
              <input
                type="text"
                value={account.csv_date_format ?? 'Y/m/d'}
                onChange={e => onUpdate(index, 'csv_date_format', e.target.value)}
                className="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:outline-none"
              />
            </div>
          </div>
        </details>
      </div>
      <div className="flex justify-end">
        <button
          type="button"
          onClick={() => onRemove(index)}
          className="text-xs text-red-500 hover:text-red-700"
        >
          {t('settings.removeBankAccount')}
        </button>
      </div>
    </div>
  )
}

// ------- Full Settings Form -------
interface SettingsFormProps {
  settings: ClearSettings
}

function SettingsForm({ settings }: SettingsFormProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const [upstreamUrl, setUpstreamUrl] = useState(settings.upstream_base_url)
  const [upstreamToken, setUpstreamToken] = useState(settings.upstream_token_ref)
  const [dunningInterval, setDunningInterval] = useState(settings.dunning_min_interval_days)
  const [bankAccounts, setBankAccounts] = useState<BankAccount[]>(settings.bank_accounts)

  const [saveSuccess, setSaveSuccess] = useState(false)
  const [testResult, setTestResult] = useState<'ok' | 'fail' | null>(null)
  const [testing, setTesting] = useState(false)

  const saveMutation = useMutation({
    mutationFn: () =>
      updateClearSettings({
        upstream_base_url: upstreamUrl,
        upstream_token_ref: upstreamToken,
        dunning_min_interval_days: dunningInterval,
        bank_accounts: bankAccounts,
      } as Partial<ClearSettings>),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['clear-settings'] })
      setSaveSuccess(true)
      setTimeout(() => setSaveSuccess(false), 3000)
    },
  })

  async function handleTestConnection() {
    setTestResult(null)
    setTesting(true)
    try {
      const res = await testUpstreamConnection()
      setTestResult(res.ok ? 'ok' : 'fail')
    } catch {
      setTestResult('fail')
    } finally {
      setTesting(false)
    }
  }

  function updateAccount(index: number, field: keyof BankAccount, value: string | number) {
    setBankAccounts(prev => prev.map((acc, i) => i === index ? { ...acc, [field]: value } : acc))
  }

  function removeAccount(index: number) {
    setBankAccounts(prev => prev.filter((_, i) => i !== index))
  }

  function addAccount() {
    setBankAccounts(prev => [...prev, {
      bank_name: '',
      bank_branch: '',
      account_type: 'ordinary',
      account_number: '',
      csv_encoding: 'utf8',
      csv_date_format: 'Y/m/d',
      csv_date_column: 0,
      csv_amount_column: 1,
      csv_counterparty_column: 2,
      csv_header_rows: 1,
    }])
  }

  return (
    <div className="space-y-8">
      {/* Upstream section */}
      <section>
        <h3 className="mb-3 text-sm font-semibold text-gray-700 border-b border-gray-100 pb-2">
          Invoice API
        </h3>
        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{t('settings.upstreamUrl')}</label>
            <input
              type="url"
              value={upstreamUrl}
              onChange={e => setUpstreamUrl(e.target.value)}
              placeholder="https://invoice.example.com"
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{t('settings.upstreamToken')}</label>
            <input
              type="password"
              value={upstreamToken}
              onChange={e => setUpstreamToken(e.target.value)}
              placeholder="Bearer token"
              className="w-full rounded border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:outline-none"
            />
          </div>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={handleTestConnection}
              disabled={testing}
              className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              {testing ? t('common.loading') : t('settings.testConnection')}
            </button>
            {testResult === 'ok' && <span className="text-sm text-green-600">{t('settings.connectionOk')}</span>}
            {testResult === 'fail' && <span className="text-sm text-red-600">{t('settings.connectionFail')}</span>}
          </div>
        </div>
      </section>

      {/* Dunning section */}
      <section>
        <h3 className="mb-3 text-sm font-semibold text-gray-700 border-b border-gray-100 pb-2">
          督促
        </h3>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">{t('settings.dunningInterval')}</label>
          <input
            type="number"
            min="1"
            value={dunningInterval}
            onChange={e => setDunningInterval(Number(e.target.value))}
            className="rounded border border-gray-300 px-3 py-2 text-sm w-32 focus:border-blue-500 focus:outline-none"
          />
        </div>
      </section>

      {/* Bank accounts section */}
      <section>
        <div className="flex items-center justify-between border-b border-gray-100 pb-2 mb-3">
          <h3 className="text-sm font-semibold text-gray-700">{t('settings.bankAccounts')}</h3>
          <button
            type="button"
            onClick={addAccount}
            className="rounded bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
          >
            + {t('settings.addBankAccount')}
          </button>
        </div>
        {bankAccounts.length === 0 && (
          <p className="text-sm text-gray-400">{t('common.noData')}</p>
        )}
        <div className="space-y-3">
          {bankAccounts.map((acc, i) => (
            <BankAccountRow
              key={i}
              account={acc}
              index={i}
              onUpdate={updateAccount}
              onRemove={removeAccount}
            />
          ))}
        </div>
      </section>

      {saveMutation.isError && (
        <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{saveMutation.error.message}</p>
      )}
      {saveSuccess && (
        <p className="rounded bg-green-50 px-3 py-2 text-sm text-green-700">{t('settings.saved')}</p>
      )}

      <button
        type="button"
        onClick={() => saveMutation.mutate()}
        disabled={saveMutation.isPending}
        className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
      >
        {saveMutation.isPending ? t('common.loading') : t('settings.save')}
      </button>
    </div>
  )
}

export default function SettingsPage() {
  const { t } = useTranslation()

  const settingsQuery = useQuery({
    queryKey: ['clear-settings'],
    queryFn: ({ signal }) => getClearSettings(signal),
  })

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('settings.title')}</h1>

      {settingsQuery.isLoading && <p className="text-sm text-gray-400">{t('common.loading')}</p>}
      {settingsQuery.isError && (
        <p className="text-sm text-red-600">{settingsQuery.error.message}</p>
      )}

      {settingsQuery.data && (
        <div className="rounded-lg bg-white p-6 shadow-sm max-w-2xl">
          <SettingsForm settings={settingsQuery.data} />
        </div>
      )}
    </div>
  )
}
