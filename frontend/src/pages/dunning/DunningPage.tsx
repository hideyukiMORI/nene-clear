import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listDunningNotices, sendDunningNotice, listUpstreamInvoices } from '@/api/endpoints'
import type { UpstreamInvoice } from '@/types'

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

function formatDate(iso: string) {
  return iso.slice(0, 16).replace('T', ' ')
}

// ------- Send Confirm Modal -------
interface SendModalProps {
  invoice: UpstreamInvoice
  onClose: () => void
}

function SendConfirmModal({ invoice, onClose }: SendModalProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: () => sendDunningNotice(invoice.invoice_id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['dunning-notices'] })
      void qc.invalidateQueries({ queryKey: ['upstream-invoices'] })
      onClose()
    },
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 className="mb-2 text-base font-semibold text-gray-900">{t('dunning.confirmSend')}</h3>
        <p className="mb-1 text-sm text-gray-700">
          {invoice.invoice_number} — {formatYen(invoice.outstanding_cents)} 未収
        </p>
        <p className="mb-4 text-xs text-gray-400">期限: {invoice.due_at}</p>
        {mutation.isError && (
          <p className="mb-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{mutation.error.message}</p>
        )}
        <div className="flex gap-3 justify-end">
          <button
            onClick={onClose}
            className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            {t('common.cancel')}
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending}
            className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {mutation.isPending ? t('common.loading') : t('dunning.send')}
          </button>
        </div>
      </div>
    </div>
  )
}

export default function DunningPage() {
  const { t } = useTranslation()
  const [sendTarget, setSendTarget] = useState<UpstreamInvoice | null>(null)

  const invoicesQuery = useQuery({
    queryKey: ['upstream-invoices', { status: 'issued,partially_paid,overdue' }],
    queryFn: ({ signal }) => listUpstreamInvoices({ status: 'issued,partially_paid,overdue' }, signal),
  })

  const noticesQuery = useQuery({
    queryKey: ['dunning-notices'],
    queryFn: ({ signal }) => listDunningNotices({ limit: 50 }, signal),
  })

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('dunning.title')}</h1>

      {/* Eligible invoices table */}
      <div className="mb-8 rounded-lg bg-white shadow-sm overflow-hidden">
        <div className="p-6 pb-3">
          <h2 className="text-base font-semibold text-gray-700">{t('dunning.eligibleInvoices')}</h2>
          <p className="text-xs text-gray-400 mt-0.5">{t('dunning.selectInvoice')}</p>
        </div>

        {invoicesQuery.isLoading && (
          <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
        )}
        {invoicesQuery.isError && (
          <p className="px-6 py-4 text-sm text-red-600">{invoicesQuery.error.message}</p>
        )}
        {invoicesQuery.data && (
          invoicesQuery.data.items.length === 0
            ? <p className="px-6 py-4 text-sm text-gray-400">{t('dunning.noEligible')}</p>
            : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                    <th className="px-6 py-3">請求書番号</th>
                    <th className="px-6 py-3">ステータス</th>
                    <th className="px-6 py-3">{t('dunning.outstanding')}</th>
                    <th className="px-6 py-3">期限</th>
                    <th className="px-6 py-3"></th>
                  </tr>
                </thead>
                <tbody>
                  {invoicesQuery.data.items.map((inv, i) => (
                    <tr
                      key={inv.invoice_id}
                      className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                    >
                      <td className="px-6 py-3 font-medium text-gray-800">{inv.invoice_number}</td>
                      <td className="px-6 py-3">
                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                          inv.status === 'overdue'
                            ? 'bg-red-100 text-red-800'
                            : inv.status === 'partially_paid'
                              ? 'bg-yellow-100 text-yellow-800'
                              : 'bg-blue-100 text-blue-800'
                        }`}>
                          {inv.status}
                        </span>
                      </td>
                      <td className="px-6 py-3 font-medium tabular-nums text-gray-900">
                        {formatYen(inv.outstanding_cents)}
                      </td>
                      <td className="px-6 py-3 text-gray-600">{inv.due_at}</td>
                      <td className="px-6 py-3">
                        <button
                          onClick={() => setSendTarget(inv)}
                          className="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                        >
                          {t('dunning.send')}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )
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

      {sendTarget && (
        <SendConfirmModal invoice={sendTarget} onClose={() => setSendTarget(null)} />
      )}
    </div>
  )
}
