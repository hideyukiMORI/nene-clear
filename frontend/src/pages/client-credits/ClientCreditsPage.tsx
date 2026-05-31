import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listClientCredits, applyClientCredit } from '@/api/endpoints'
import type { ClientCredit } from '@/types'

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

// ------- Apply Credit Modal -------
interface ApplyModalProps {
  credit: ClientCredit
  onClose: () => void
}

function ApplyCreditModal({ credit, onClose }: ApplyModalProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [invoiceIdInput, setInvoiceIdInput] = useState('')
  const [amountInput, setAmountInput] = useState(String(credit.remaining_cents / 100))

  const mutation = useMutation({
    mutationFn: () =>
      applyClientCredit(
        credit.client_credit_id,
        Number(invoiceIdInput),
        Math.round(Number(amountInput) * 100),
      ),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['client-credits'] })
      onClose()
    },
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 className="mb-1 text-base font-semibold text-gray-900">{t('clientCredit.applyModal.title')}</h3>
        <p className="mb-4 text-xs text-gray-400">
          残高: {formatYen(credit.remaining_cents)} (元取引 #{credit.source_bank_transaction_id})
        </p>

        <div className="mb-3">
          <label className="block text-sm font-medium text-gray-700 mb-1">{t('clientCredit.applyModal.invoiceId')}</label>
          <input
            type="number"
            min="1"
            value={invoiceIdInput}
            onChange={e => setInvoiceIdInput(e.target.value)}
            placeholder="例: 123"
            className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none"
          />
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">{t('clientCredit.applyModal.amount')}</label>
          <input
            type="number"
            min="1"
            step="1"
            value={amountInput}
            onChange={e => setAmountInput(e.target.value)}
            className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none"
          />
        </div>

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
            disabled={mutation.isPending || !invoiceIdInput || !amountInput}
            className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {mutation.isPending ? t('common.loading') : t('clientCredit.apply')}
          </button>
        </div>
      </div>
    </div>
  )
}

// ------- Status Badge -------
function StatusBadge({ status }: { status: ClientCredit['status'] }) {
  const { t } = useTranslation()
  const styles: Record<ClientCredit['status'], string> = {
    open: 'bg-green-100 text-green-800',
    partially_applied: 'bg-yellow-100 text-yellow-800',
    applied: 'bg-gray-200 text-gray-600',
  }
  const keys: Record<ClientCredit['status'], Parameters<typeof t>[0]> = {
    open: 'clientCredit.status.open',
    partially_applied: 'clientCredit.status.partially_applied',
    applied: 'clientCredit.status.applied',
  }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status]}`}>
      {t(keys[status])}
    </span>
  )
}

// ------- Main Page -------
export default function ClientCreditsPage() {
  const { t } = useTranslation()
  const [applyTarget, setApplyTarget] = useState<ClientCredit | null>(null)

  const creditsQuery = useQuery({
    queryKey: ['client-credits'],
    queryFn: ({ signal }) => listClientCredits({ limit: 100 }, signal),
  })

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('clientCredit.title')}</h1>

      <div className="rounded-lg bg-white shadow-sm overflow-hidden">
        {creditsQuery.isLoading && (
          <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
        )}
        {creditsQuery.isError && (
          <p className="px-6 py-4 text-sm text-red-600">{creditsQuery.error.message}</p>
        )}
        {creditsQuery.data && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                <th className="px-6 py-3">ID</th>
                <th className="px-6 py-3">クライアントID</th>
                <th className="px-6 py-3">金額</th>
                <th className="px-6 py-3">{t('clientCredit.remaining')}</th>
                <th className="px-6 py-3">ステータス</th>
                <th className="px-6 py-3">{t('clientCredit.sourceTransaction')}</th>
                <th className="px-6 py-3">登録日</th>
                <th className="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {creditsQuery.data.items.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-6 py-4 text-center text-gray-400">
                    {t('common.noData')}
                  </td>
                </tr>
              )}
              {creditsQuery.data.items.map((credit, i) => (
                <tr
                  key={credit.client_credit_id}
                  className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                >
                  <td className="px-6 py-3 text-gray-500">{credit.client_credit_id}</td>
                  <td className="px-6 py-3 text-gray-700">{credit.client_id}</td>
                  <td className="px-6 py-3 font-medium tabular-nums text-gray-900">
                    {formatYen(credit.amount_cents)}
                  </td>
                  <td className="px-6 py-3 font-medium tabular-nums text-gray-900">
                    {formatYen(credit.remaining_cents)}
                  </td>
                  <td className="px-6 py-3">
                    <StatusBadge status={credit.status} />
                  </td>
                  <td className="px-6 py-3 text-gray-600">#{credit.source_bank_transaction_id}</td>
                  <td className="px-6 py-3 text-gray-500">{credit.created_at.slice(0, 10)}</td>
                  <td className="px-6 py-3">
                    {credit.status !== 'applied' && (
                      <button
                        onClick={() => setApplyTarget(credit)}
                        className="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                      >
                        {t('clientCredit.apply')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {applyTarget && (
        <ApplyCreditModal credit={applyTarget} onClose={() => setApplyTarget(null)} />
      )}
    </div>
  )
}
