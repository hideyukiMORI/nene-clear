import { useQuery } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listUnmatchedTransactions, listDunningNotices } from '@/api/endpoints'

function formatYen(cents: number) {
  return '¥' + Math.floor(cents / 100).toLocaleString('ja-JP')
}

function formatDate(iso: string) {
  return iso.slice(0, 10)
}

export default function DashboardPage() {
  const { t } = useTranslation()

  const unmatchedQuery = useQuery({
    queryKey: ['bank-transactions', 'unmatched', { limit: 1 }],
    queryFn: ({ signal }) => listUnmatchedTransactions({ limit: 1 }, signal),
  })

  const dunningQuery = useQuery({
    queryKey: ['dunning-notices', { limit: 5 }],
    queryFn: ({ signal }) => listDunningNotices({ limit: 5 }, signal),
  })

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('dashboard.title')}</h1>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        {/* Unmatched transactions card */}
        <div className="rounded-lg bg-white p-6 shadow-sm">
          <h2 className="mb-4 text-base font-semibold text-gray-700">{t('dashboard.unmatched')}</h2>
          {unmatchedQuery.isLoading && (
            <p className="text-sm text-gray-400">{t('common.loading')}</p>
          )}
          {unmatchedQuery.isError && (
            <p className="text-sm text-red-600">{unmatchedQuery.error.message}</p>
          )}
          {unmatchedQuery.data && (
            <p className="text-4xl font-bold text-gray-900">
              {unmatchedQuery.data.total}
              <span className="ml-2 text-sm font-normal text-gray-500">件</span>
            </p>
          )}
        </div>

        {/* Recent dunning notices card */}
        <div className="rounded-lg bg-white p-6 shadow-sm">
          <h2 className="mb-4 text-base font-semibold text-gray-700">{t('dashboard.recentDunning')}</h2>
          {dunningQuery.isLoading && (
            <p className="text-sm text-gray-400">{t('common.loading')}</p>
          )}
          {dunningQuery.isError && (
            <p className="text-sm text-red-600">{dunningQuery.error.message}</p>
          )}
          {dunningQuery.data && dunningQuery.data.items.length === 0 && (
            <p className="text-sm text-gray-400">{t('common.noData')}</p>
          )}
          {dunningQuery.data && dunningQuery.data.items.length > 0 && (
            <ul className="space-y-2">
              {dunningQuery.data.items.map(notice => (
                <li key={notice.dunning_notice_id} className="flex items-center justify-between text-sm">
                  <span className="font-medium text-gray-800">{notice.invoice_number}</span>
                  <span className="text-gray-500">{formatYen(notice.outstanding_at_send_cents)}</span>
                  <span className="text-gray-400">{formatDate(notice.sent_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  )
}
