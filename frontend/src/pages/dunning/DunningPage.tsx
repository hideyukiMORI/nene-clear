import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import {
  listDunningNotices, sendDunningNotice,
  listDunningPauses, pauseDunningNotice, resumeDunningNotice,
} from '@/api/endpoints'

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

function formatDate(iso: string) {
  return iso.slice(0, 16).replace('T', ' ')
}

// ------- Pause Modal -------
interface PauseModalProps {
  invoiceId: number
  onClose: () => void
}

function PauseModal({ invoiceId, onClose }: PauseModalProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [reason, setReason] = useState('')

  const mutation = useMutation({
    mutationFn: () => pauseDunningNotice(invoiceId, reason),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['dunning-pauses'] })
      onClose()
    },
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('dunning.confirmPause')}</h3>
        <p className="mb-3 text-sm text-gray-500">請求書ID: {invoiceId}</p>
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">{t('dunning.pauseReason')}</label>
          <input
            type="text"
            value={reason}
            onChange={e => setReason(e.target.value)}
            className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none"
          />
        </div>
        {mutation.isError && (
          <p className="mb-3 text-sm text-red-600">{mutation.error.message}</p>
        )}
        <div className="flex gap-3 justify-end">
          <button onClick={onClose} className="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            {t('common.cancel')}
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending || !reason.trim()}
            className="rounded bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-50"
          >
            {mutation.isPending ? t('common.loading') : t('dunning.pause')}
          </button>
        </div>
      </div>
    </div>
  )
}

export default function DunningPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const [invoiceIdInput, setInvoiceIdInput] = useState('')
  const [sendSuccess, setSendSuccess] = useState(false)
  const [sendError, setSendError] = useState<string | null>(null)
  const [pauseTarget, setPauseTarget] = useState<number | null>(null)

  const noticesQuery = useQuery({
    queryKey: ['dunning-notices'],
    queryFn: ({ signal }) => listDunningNotices({ limit: 50 }, signal),
  })

  const pausesQuery = useQuery({
    queryKey: ['dunning-pauses', { active_only: true }],
    queryFn: ({ signal }) => listDunningPauses({ active_only: true, limit: 100 }, signal),
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

  const resumeMutation = useMutation({
    mutationFn: (invoiceId: number) => resumeDunningNotice(invoiceId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['dunning-pauses'] })
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

  const activePauseIds = new Set((pausesQuery.data?.items ?? []).map(p => p.invoice_id))

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
          <button
            type="button"
            disabled={!invoiceIdInput}
            onClick={() => invoiceIdInput && setPauseTarget(Number(invoiceIdInput))}
            className="rounded border border-orange-300 px-4 py-2 text-sm font-medium text-orange-700 hover:bg-orange-50 disabled:opacity-40"
          >
            {t('dunning.pause')}
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

      {/* Active pauses */}
      {activePauseIds.size > 0 && (
        <div className="mb-6 rounded-lg bg-orange-50 border border-orange-200 p-4">
          <h2 className="mb-2 text-sm font-semibold text-orange-800">{t('dunning.paused')}（{activePauseIds.size}件）</h2>
          <div className="flex flex-wrap gap-2">
            {(pausesQuery.data?.items ?? []).map(p => (
              <div key={p.dunning_pause_id} className="flex items-center gap-2 rounded bg-white border border-orange-200 px-3 py-1 text-xs">
                <span className="font-medium text-gray-800">請求書 #{p.invoice_id}</span>
                <span className="text-gray-400">{p.paused_reason}</span>
                <button
                  onClick={() => resumeMutation.mutate(p.invoice_id)}
                  disabled={resumeMutation.isPending}
                  className="text-blue-600 hover:underline"
                >
                  {t('dunning.resume')}
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

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

      {pauseTarget !== null && (
        <PauseModal invoiceId={pauseTarget} onClose={() => setPauseTarget(null)} />
      )}
    </div>
  )
}
