import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listBankTransactions } from '@/api/endpoints'
import type { BankTransaction } from '@/types'

const PAGE_SIZE = 20

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

function StatusBadge({ status }: { status: BankTransaction['status'] }) {
  const { t } = useTranslation()
  const styles: Record<BankTransaction['status'], string> = {
    unmatched: 'bg-yellow-100 text-yellow-800',
    partially_matched: 'bg-blue-100 text-blue-800',
    matched: 'bg-green-100 text-green-800',
    voided: 'bg-gray-200 text-gray-500',
  }
  const keys: Record<BankTransaction['status'], Parameters<typeof t>[0]> = {
    unmatched: 'bankTransaction.status.unmatched',
    partially_matched: 'bankTransaction.status.partially_matched',
    matched: 'bankTransaction.status.matched',
    voided: 'bankTransaction.status.voided',
  }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status]}`}>
      {t(keys[status])}
    </span>
  )
}

export default function BankTransactionsPage() {
  const { t } = useTranslation()

  const [status, setStatus] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [amountMin, setAmountMin] = useState('')
  const [amountMax, setAmountMax] = useState('')
  const [counterparty, setCounterparty] = useState('')
  // Applied filter state (only changes on "Search" submit)
  const [appliedFilter, setAppliedFilter] = useState({
    status: '',
    dateFrom: '',
    dateTo: '',
    amountMin: '',
    amountMax: '',
    counterparty: '',
    offset: 0,
  })

  const txQuery = useQuery({
    queryKey: ['bank-transactions', appliedFilter],
    queryFn: ({ signal }) =>
      listBankTransactions(
        {
          status: appliedFilter.status || undefined,
          value_date_from: appliedFilter.dateFrom || undefined,
          value_date_to: appliedFilter.dateTo || undefined,
          amount_min_cents: appliedFilter.amountMin ? Math.round(Number(appliedFilter.amountMin) * 100) : undefined,
          amount_max_cents: appliedFilter.amountMax ? Math.round(Number(appliedFilter.amountMax) * 100) : undefined,
          counterparty: appliedFilter.counterparty || undefined,
          limit: PAGE_SIZE,
          offset: appliedFilter.offset,
        },
        signal,
      ),
  })

  function applyFilter() {
    setAppliedFilter({ status, dateFrom, dateTo, amountMin, amountMax, counterparty, offset: 0 })
  }

  function goPage(newOffset: number) {
    setAppliedFilter(prev => ({ ...prev, offset: newOffset }))
  }

  const total = txQuery.data?.total ?? 0
  const currentPage = Math.floor(appliedFilter.offset / PAGE_SIZE) + 1
  const totalPages = Math.ceil(total / PAGE_SIZE)

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('bankTransaction.title')}</h1>

      {/* Filter bar */}
      <div className="mb-6 rounded-lg bg-white p-4 shadow-sm flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('common.filter')} ステータス</label>
          <select
            value={status}
            onChange={e => setStatus(e.target.value)}
            className="rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">{t('common.all')}</option>
            <option value="unmatched">{t('bankTransaction.status.unmatched')}</option>
            <option value="partially_matched">{t('bankTransaction.status.partially_matched')}</option>
            <option value="matched">{t('bankTransaction.status.matched')}</option>
            <option value="voided">{t('bankTransaction.status.voided')}</option>
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('bankTransaction.valueDate')} 開始</label>
          <input
            type="date"
            value={dateFrom}
            onChange={e => setDateFrom(e.target.value)}
            className="rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('bankTransaction.valueDate')} 終了</label>
          <input
            type="date"
            value={dateTo}
            onChange={e => setDateTo(e.target.value)}
            className="rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('bankTransaction.amountFrom')}</label>
          <input
            type="number"
            min="0"
            step="1"
            value={amountMin}
            onChange={e => setAmountMin(e.target.value)}
            placeholder="0"
            className="rounded border border-gray-300 px-2 py-1.5 text-sm w-28 focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('bankTransaction.amountTo')}</label>
          <input
            type="number"
            min="0"
            step="1"
            value={amountMax}
            onChange={e => setAmountMax(e.target.value)}
            placeholder="—"
            className="rounded border border-gray-300 px-2 py-1.5 text-sm w-28 focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('bankTransaction.counterparty')}</label>
          <input
            type="text"
            value={counterparty}
            onChange={e => setCounterparty(e.target.value)}
            placeholder={t('common.search')}
            className="rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>

        <button
          onClick={applyFilter}
          className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          {t('common.search')}
        </button>
      </div>

      {/* Table */}
      <div className="rounded-lg bg-white shadow-sm overflow-hidden">
        {txQuery.isLoading && (
          <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
        )}
        {txQuery.isError && (
          <p className="px-6 py-4 text-sm text-red-600">{txQuery.error.message}</p>
        )}
        {txQuery.data && (
          <>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                  <th className="px-6 py-3">{t('bankTransaction.valueDate')}</th>
                  <th className="px-6 py-3">{t('bankTransaction.amount')}</th>
                  <th className="px-6 py-3">{t('bankTransaction.counterparty')}</th>
                  <th className="px-6 py-3">ステータス</th>
                </tr>
              </thead>
              <tbody>
                {txQuery.data.items.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-6 py-4 text-center text-gray-400">
                      {t('common.noData')}
                    </td>
                  </tr>
                )}
                {txQuery.data.items.map((tx, i) => (
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
                      <StatusBadge status={tx.status} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Pagination */}
            {total > PAGE_SIZE && (
              <div className="flex items-center justify-between border-t border-gray-200 px-6 py-3 text-sm text-gray-600">
                <span>
                  {t('common.pagination.showing', {
                    from: appliedFilter.offset + 1,
                    to: Math.min(appliedFilter.offset + PAGE_SIZE, total),
                    total,
                  })}
                </span>
                <div className="flex gap-2">
                  <button
                    onClick={() => goPage(Math.max(0, appliedFilter.offset - PAGE_SIZE))}
                    disabled={currentPage === 1}
                    className="rounded border border-gray-300 px-3 py-1 disabled:opacity-40 hover:bg-gray-50"
                  >
                    {t('common.back')}
                  </button>
                  <span className="px-2 py-1">{currentPage} / {totalPages}</span>
                  <button
                    onClick={() => goPage(appliedFilter.offset + PAGE_SIZE)}
                    disabled={currentPage >= totalPages}
                    className="rounded border border-gray-300 px-3 py-1 disabled:opacity-40 hover:bg-gray-50"
                  >
                    {t('common.next')}
                  </button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
