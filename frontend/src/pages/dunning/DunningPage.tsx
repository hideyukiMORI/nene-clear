import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listDunningNotices, sendDunningNotice } from '@/api/endpoints'

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

function formatDate(iso: string) {
  return iso.slice(0, 16).replace('T', ' ')
}

export default function DunningPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const [invoiceIdInput, setInvoiceIdInput] = useState('')
  const [sendSuccess, setSendSuccess] = useState(false)
  const [sendError, setSendError] = useState<string | null>(null)

  const noticesQuery = useQuery({
    queryKey: ['dunning-notices'],
    queryFn: ({ signal }) => listDunningNotices({ limit: 50 }, signal),
  })

  const sendMutation = useMutation({
    mutationFn: (invoiceId: number) => sendDunningNotice(invoiceId),
    onSuccess: () => {
      setSendSuccess(true)
      setSendError(null)
      setInvoiceIdInput('')
      void qc.invalidateQueries({ queryKey: ['dunning-notices'] })
    },
    onError: (err: Error) => {
      setSendSuccess(false)
      setSendError(err.message)
    },
  })

  function handleSend(e: React.FormEvent) {
    e.preventDefault()
    setSendSuccess(false)
    setSendError(null)
    const id = Number(invoiceIdInput)
    if (!id || id <= 0) {
      setSendError('有効な請求書IDを入力してください')
      return
    }
    sendMutation.mutate(id)
  }

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('dunning.title')}</h1>

      {/* Send form */}
      <div className="mb-8 rounded-lg bg-white p-6 shadow-sm">
        <h2 className="mb-4 text-base font-semibold text-gray-700">{t('dunning.send')}</h2>
        <form onSubmit={handleSend} className="flex flex-wrap gap-3 items-end">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('dunning.invoiceId')}
            </label>
            <input
              type="number"
              min="1"
              value={invoiceIdInput}
              onChange={e => setInvoiceIdInput(e.target.value)}
              placeholder="例: 123"
              className="rounded border border-gray-300 px-3 py-2 text-sm w-40 focus:border-blue-500 focus:outline-none"
            />
          </div>
          <button
            type="submit"
            disabled={sendMutation.isPending}
            className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {sendMutation.isPending ? t('common.loading') : t('dunning.send')}
          </button>
        </form>

        {sendError && (
          <p className="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{sendError}</p>
        )}
        {sendSuccess && (
          <p className="mt-3 rounded bg-green-50 px-3 py-2 text-sm text-green-700">
            督促メールを送信しました。
          </p>
        )}
      </div>

      {/* History table */}
      <div className="rounded-lg bg-white shadow-sm overflow-hidden">
        <div className="p-6 pb-3">
          <h2 className="text-base font-semibold text-gray-700">{t('dunning.history')}</h2>
        </div>
        {noticesQuery.isLoading && (
          <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
        )}
        {noticesQuery.isError && (
          <p className="px-6 py-4 text-sm text-red-600">{noticesQuery.error.message}</p>
        )}
        {noticesQuery.data && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                <th className="px-6 py-3">請求書番号</th>
                <th className="px-6 py-3">{t('dunning.recipient')}</th>
                <th className="px-6 py-3">{t('dunning.outstanding')}</th>
                <th className="px-6 py-3">{t('dunning.sentAt')}</th>
                <th className="px-6 py-3">{t('dunning.sentBy')}</th>
              </tr>
            </thead>
            <tbody>
              {noticesQuery.data.items.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-6 py-4 text-center text-gray-400">
                    {t('common.noData')}
                  </td>
                </tr>
              )}
              {noticesQuery.data.items.map((notice, i) => (
                <tr
                  key={notice.dunning_notice_id}
                  className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                >
                  <td className="px-6 py-3 font-medium text-gray-800">{notice.invoice_number}</td>
                  <td className="px-6 py-3 text-gray-700">{notice.recipient_email}</td>
                  <td className="px-6 py-3 font-medium tabular-nums text-gray-900">
                    {formatYen(notice.outstanding_at_send_cents)}
                  </td>
                  <td className="px-6 py-3 text-gray-600">{formatDate(notice.sent_at)}</td>
                  <td className="px-6 py-3 text-gray-500">{notice.sent_by}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}
