import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import {
  listUnmatchedTransactions,
  listReconciliations,
  confirmMatch,
  reverseReconciliation,
  proposeMatch,
  downloadCsv,
} from '@/api/endpoints'
import type { BankTransaction, Reconciliation, UpstreamInvoice } from '@/types'
import type { AllocationInput } from '@/api/endpoints'

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

function formatDate(iso: string) {
  return iso.slice(0, 10)
}

// ------- Confirm Match Modal -------
interface MatchModalProps {
  transaction: BankTransaction
  onClose: () => void
}

function ConfirmMatchModal({ transaction, onClose }: MatchModalProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const [allocations, setAllocations] = useState<AllocationInput[]>([
    { invoice_id: 0, amount_cents: 0 },
  ])
  const [reasonCode, setReasonCode] = useState('')

  // Load match suggestions on modal open
  const suggestionsQuery = useQuery({
    queryKey: ['propose-match', transaction.bank_transaction_id],
    queryFn: () => proposeMatch(transaction.bank_transaction_id),
    retry: false,
  })

  const mutation = useMutation({
    mutationFn: () =>
      confirmMatch(
        transaction.bank_transaction_id,
        allocations.filter(a => a.invoice_id > 0 && a.amount_cents > 0),
        reasonCode || undefined,
      ),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['bank-transactions', 'unmatched'] })
      void qc.invalidateQueries({ queryKey: ['reconciliations'] })
      onClose()
    },
  })

  function applySuggestion(invoice: UpstreamInvoice) {
    setAllocations([{ invoice_id: invoice.invoice_id, amount_cents: invoice.outstanding_cents }])
  }

  function updateAllocation(idx: number, field: keyof AllocationInput, value: string) {
    setAllocations(prev =>
      prev.map((a, i) =>
        i === idx ? { ...a, [field]: field === 'amount_cents' ? Math.round(Number(value) * 100) : Number(value) } : a,
      ),
    )
  }

  function addAllocation() {
    setAllocations(prev => [...prev, { invoice_id: 0, amount_cents: 0 }])
  }

  function removeAllocation(idx: number) {
    setAllocations(prev => prev.filter((_, i) => i !== idx))
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 className="mb-1 text-base font-semibold text-gray-900">{t('reconciliation.confirm')}</h3>
        <p className="mb-4 text-sm text-gray-500">
          {transaction.counterparty_text} — {formatYen(transaction.amount_cents)} ({transaction.value_date})
        </p>

        {/* Suggestions panel */}
        <div className="mb-4 rounded bg-blue-50 p-3">
          <p className="text-xs font-semibold text-blue-700 mb-2">{t('reconciliation.suggestions')}</p>
          {suggestionsQuery.isLoading && (
            <p className="text-xs text-gray-400">{t('common.loading')}</p>
          )}
          {suggestionsQuery.data && suggestionsQuery.data.invoices.length === 0 && (
            <p className="text-xs text-gray-400">{t('reconciliation.noSuggestions')}</p>
          )}
          {suggestionsQuery.data && suggestionsQuery.data.invoices.map(inv => (
            <div key={inv.invoice_id} className="flex items-center justify-between py-1 border-b border-blue-100 last:border-0 text-xs">
              <span className="font-medium text-gray-800">{inv.invoice_number}</span>
              <span className="text-gray-600">{formatYen(inv.outstanding_cents)}</span>
              <span className="text-gray-400">{inv.due_at}</span>
              <button
                type="button"
                onClick={() => applySuggestion(inv)}
                className="ml-2 rounded bg-blue-600 px-2 py-0.5 text-white hover:bg-blue-700"
              >
                {t('reconciliation.applySuggestion')}
              </button>
            </div>
          ))}
        </div>

        {/* Allocations */}
        <div className="mb-4 space-y-2">
          {allocations.map((a, i) => (
            <div key={i} className="flex gap-2 items-center">
              <div className="flex-1">
                <label className="block text-xs text-gray-500 mb-0.5">{t('reconciliation.invoice')} ID</label>
                <input
                  type="number"
                  min="1"
                  value={a.invoice_id || ''}
                  onChange={e => updateAllocation(i, 'invoice_id', e.target.value)}
                  className="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none"
                />
              </div>
              <div className="flex-1">
                <label className="block text-xs text-gray-500 mb-0.5">{t('reconciliation.allocation')} (¥)</label>
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={a.amount_cents > 0 ? a.amount_cents / 100 : ''}
                  onChange={e => updateAllocation(i, 'amount_cents', e.target.value)}
                  className="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none"
                />
              </div>
              {allocations.length > 1 && (
                <button
                  type="button"
                  onClick={() => removeAllocation(i)}
                  className="mt-4 text-gray-400 hover:text-red-500 text-lg leading-none"
                >
                  ×
                </button>
              )}
            </div>
          ))}
        </div>

        <button
          type="button"
          onClick={addAllocation}
          className="mb-4 text-sm text-blue-600 hover:underline"
        >
          + {t('reconciliation.addAllocation')}
        </button>

        {/* Reason code (optional) */}
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            理由コード（任意）
          </label>
          <input
            type="text"
            value={reasonCode}
            onChange={e => setReasonCode(e.target.value)}
            className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none"
          />
        </div>

        {mutation.isError && (
          <p className="mb-3 text-sm text-red-600">{mutation.error.message}</p>
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
            {mutation.isPending ? t('common.loading') : t('reconciliation.confirm')}
          </button>
        </div>
      </div>
    </div>
  )
}

// ------- Reverse Reconciliation Modal -------
interface ReverseModalProps {
  reconciliation: Reconciliation
  onClose: () => void
}

function ReverseModal({ reconciliation, onClose }: ReverseModalProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [reason, setReason] = useState('')

  const mutation = useMutation({
    mutationFn: () => reverseReconciliation(reconciliation.payment_reconciliation_id, reason),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['reconciliations'] })
      void qc.invalidateQueries({ queryKey: ['bank-transactions'] })
      onClose()
    },
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 className="mb-4 text-base font-semibold text-gray-900">{t('reconciliation.confirmReverse')}</h3>
        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            {t('reconciliation.reversalReason')}
          </label>
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
          <button
            onClick={onClose}
            className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            {t('common.cancel')}
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending || !reason.trim()}
            className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          >
            {mutation.isPending ? t('common.loading') : t('reconciliation.reverse')}
          </button>
        </div>
      </div>
    </div>
  )
}

// ------- Main Page -------
export default function ReconciliationPage() {
  const { t } = useTranslation()
  const [tab, setTab] = useState<'unmatched' | 'history'>('unmatched')
  const [matchTarget, setMatchTarget] = useState<BankTransaction | null>(null)
  const [reverseTarget, setReverseTarget] = useState<Reconciliation | null>(null)

  const unmatchedQuery = useQuery({
    queryKey: ['bank-transactions', 'unmatched', { limit: 50 }],
    queryFn: ({ signal }) => listUnmatchedTransactions({ limit: 50 }, signal),
    enabled: tab === 'unmatched',
  })

  const reconciliationsQuery = useQuery({
    queryKey: ['reconciliations'],
    queryFn: ({ signal }) => listReconciliations({ limit: 50 }, signal),
    enabled: tab === 'history',
  })

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">{t('reconciliation.title')}</h1>
        <button
          onClick={() => void downloadCsv('/admin/export/reconciliations', 'reconciliations.csv')}
          className="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
        >
          {t('export.reconciliations')}
        </button>
      </div>

      {/* Tabs */}
      <div className="mb-6 flex gap-1 border-b border-gray-200">
        {(['unmatched', 'history'] as const).map(tabKey => (
          <button
            key={tabKey}
            onClick={() => setTab(tabKey)}
            className={[
              'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
              tab === tabKey
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700',
            ].join(' ')}
          >
            {tabKey === 'unmatched' ? t('bankTransaction.status.unmatched') : t('reconciliation.list')}
          </button>
        ))}
      </div>

      {/* Unmatched tab */}
      {tab === 'unmatched' && (
        <div className="rounded-lg bg-white shadow-sm overflow-hidden">
          {unmatchedQuery.isLoading && (
            <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
          )}
          {unmatchedQuery.isError && (
            <p className="px-6 py-4 text-sm text-red-600">{unmatchedQuery.error.message}</p>
          )}
          {unmatchedQuery.data && (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                  <th className="px-6 py-3">{t('bankTransaction.valueDate')}</th>
                  <th className="px-6 py-3">{t('bankTransaction.amount')}</th>
                  <th className="px-6 py-3">{t('bankTransaction.counterparty')}</th>
                  <th className="px-6 py-3"></th>
                </tr>
              </thead>
              <tbody>
                {unmatchedQuery.data.items.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-6 py-4 text-center text-gray-400">
                      {t('dashboard.noUnmatched')}
                    </td>
                  </tr>
                )}
                {unmatchedQuery.data.items.map((tx, i) => (
                  <tr
                    key={tx.bank_transaction_id}
                    className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                  >
                    <td className="px-6 py-3 text-gray-700">{tx.value_date}</td>
                    <td className="px-6 py-3 font-medium text-gray-900 tabular-nums">
                      {formatYen(tx.amount_cents)}
                    </td>
                    <td className="px-6 py-3 text-gray-700">{tx.counterparty_text}</td>
                    <td className="px-6 py-3">
                      <button
                        onClick={() => setMatchTarget(tx)}
                        className="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                      >
                        {t('reconciliation.confirm')}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* History tab */}
      {tab === 'history' && (
        <div className="rounded-lg bg-white shadow-sm overflow-hidden">
          {reconciliationsQuery.isLoading && (
            <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
          )}
          {reconciliationsQuery.isError && (
            <p className="px-6 py-4 text-sm text-red-600">{reconciliationsQuery.error.message}</p>
          )}
          {reconciliationsQuery.data && (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                  <th className="px-6 py-3">ID</th>
                  <th className="px-6 py-3">銀行取引ID</th>
                  <th className="px-6 py-3">ステータス</th>
                  <th className="px-6 py-3">確定日</th>
                  <th className="px-6 py-3"></th>
                </tr>
              </thead>
              <tbody>
                {reconciliationsQuery.data.items.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-6 py-4 text-center text-gray-400">
                      {t('common.noData')}
                    </td>
                  </tr>
                )}
                {reconciliationsQuery.data.items.map((rec, i) => (
                  <tr
                    key={rec.payment_reconciliation_id}
                    className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                  >
                    <td className="px-6 py-3 text-gray-500">{rec.payment_reconciliation_id}</td>
                    <td className="px-6 py-3 text-gray-700">{rec.bank_transaction_id}</td>
                    <td className="px-6 py-3">
                      <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        rec.status === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'
                      }`}>
                        {rec.status === 'confirmed' ? t('reconciliation.status.confirmed') : t('reconciliation.status.reversed')}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-gray-600">{formatDate(rec.confirmed_at)}</td>
                    <td className="px-6 py-3">
                      {rec.status === 'confirmed' && (
                        <button
                          onClick={() => setReverseTarget(rec)}
                          className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                        >
                          {t('reconciliation.reverse')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {matchTarget && (
        <ConfirmMatchModal transaction={matchTarget} onClose={() => setMatchTarget(null)} />
      )}
      {reverseTarget && (
        <ReverseModal reconciliation={reverseTarget} onClose={() => setReverseTarget(null)} />
      )}
    </div>
  )
}
