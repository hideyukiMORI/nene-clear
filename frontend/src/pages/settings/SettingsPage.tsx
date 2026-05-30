import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { getClearSettings, updateClearSettings, testUpstreamConnection } from '@/api/endpoints'

const schema = z.object({
  dunning_min_interval_days: z.number().int().min(1),
})

type FormValues = z.infer<typeof schema>

function SettingsForm({ defaultValues }: { defaultValues: FormValues }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [saveSuccess, setSaveSuccess] = useState(false)
  const [testResult, setTestResult] = useState<'ok' | 'fail' | null>(null)
  const [testing, setTesting] = useState(false)

  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const saveMutation = useMutation({
    mutationFn: (values: FormValues) => updateClearSettings(values),
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

  return (
    <form onSubmit={handleSubmit(v => saveMutation.mutate(v))} className="space-y-5">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          {t('settings.dunningInterval')}
        </label>
        <input
          type="number"
          {...register('dunning_min_interval_days', { valueAsNumber: true })}
          className="rounded border border-gray-300 px-3 py-2 text-sm w-32 focus:border-blue-500 focus:outline-none"
        />
        {errors.dunning_min_interval_days && (
          <p className="mt-1 text-xs text-red-600">{errors.dunning_min_interval_days.message}</p>
        )}
      </div>

      <div>
        <p className="block text-sm font-medium text-gray-700 mb-2">{t('settings.upstreamUrl')}</p>
        <button
          type="button"
          onClick={handleTestConnection}
          disabled={testing}
          className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          {testing ? t('common.loading') : t('settings.testConnection')}
        </button>
        {testResult === 'ok' && (
          <span className="ml-3 text-sm text-green-600">{t('settings.connectionOk')}</span>
        )}
        {testResult === 'fail' && (
          <span className="ml-3 text-sm text-red-600">{t('settings.connectionFail')}</span>
        )}
      </div>

      {saveMutation.isError && (
        <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{saveMutation.error.message}</p>
      )}
      {saveSuccess && (
        <p className="rounded bg-green-50 px-3 py-2 text-sm text-green-700">{t('settings.saved')}</p>
      )}

      <button
        type="submit"
        disabled={isSubmitting || saveMutation.isPending}
        className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
      >
        {saveMutation.isPending ? t('common.loading') : t('settings.save')}
      </button>
    </form>
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
        <div className="rounded-lg bg-white p-6 shadow-sm max-w-lg space-y-6">
          <SettingsForm
            defaultValues={{ dunning_min_interval_days: settingsQuery.data.dunning_min_interval_days }}
          />

          {settingsQuery.data.bank_accounts.length > 0 && (
            <div>
              <h3 className="mb-3 text-sm font-semibold text-gray-700">{t('settings.bankAccounts')}</h3>
              <ul className="space-y-2">
                {settingsQuery.data.bank_accounts.map(acc => (
                  <li
                    key={acc.bank_account_id}
                    className="rounded border border-gray-200 px-4 py-2 text-sm text-gray-700"
                  >
                    {acc.bank_name} {acc.bank_branch} — {acc.account_type} {acc.account_number}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
