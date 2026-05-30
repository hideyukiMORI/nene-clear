import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listBankImportBatches, importBankCsv, reverseBankImportBatch, getClearSettings } from '@/api/endpoints'
import type { BankImportBatch } from '@/types'

function formatDate(iso: string) {
  return iso.slice(0, 10)
}

function StatusBadge({ status }: { status: BankImportBatch['status'] }) {
  const { t } = useTranslation()
  const cls =
    status === 'imported'
      ? 'bg-green-100 text-green-800'
      : 'bg-gray-200 text-gray-600'
  const label =
    status === 'imported'
      ? t('bankImport.status.imported')
      : t('bankImport.status.reversed')
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}`}>
      {label}
    </span>
  )
}

export default function BankImportPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)

  const [selectedAccountId, setSelectedAccountId] = useState<number | ''>('')
  const [uploadError, setUploadError] = useState<string | null>(null)
  const [uploadSuccess, setUploadSuccess] = useState(false)

  const [reverseTarget, setReverseTarget] = useState<BankImportBatch | null>(null)
  const [reversalReason, setReversalReason] = useState('')

  // Fetch settings for bank accounts
  const settingsQuery = useQuery({
    queryKey: ['clear-settings'],
    queryFn: ({ signal }) => getClearSettings(signal),
  })

  // Fetch batches
  const batchQuery = useQuery({
    queryKey: ['bank-import-batches'],
    queryFn: ({ signal }) => listBankImportBatches({ limit: 50 }, signal),
  })

  // Upload mutation
  const uploadMutation = useMutation({
    mutationFn: ({ accountId, file }: { accountId: number; file: File }) =>
      importBankCsv(accountId, file),
    onSuccess: () => {
      setUploadSuccess(true)
      setUploadError(null)
      setSelectedAccountId('')
      if (fileRef.current) fileRef.current.value = ''
      void qc.invalidateQueries({ queryKey: ['bank-import-batches'] })
      void qc.invalidateQueries({ queryKey: ['bank-transactions'] })
    },
    onError: (err: Error) => {
      setUploadError(err.message)
      setUploadSuccess(false)
    },
  })

  // Reverse mutation
  const reverseMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      reverseBankImportBatch(id, reason),
    onSuccess: () => {
      setReverseTarget(null)
      setReversalReason('')
      void qc.invalidateQueries({ queryKey: ['bank-import-batches'] })
      void qc.invalidateQueries({ queryKey: ['bank-transactions'] })
    },
  })

  function handleUpload(e: React.FormEvent) {
    e.preventDefault()
    setUploadSuccess(false)
    setUploadError(null)
    const file = fileRef.current?.files?.[0]
    if (!file || selectedAccountId === '') {
      setUploadError('銀行口座とファイルを選択してください')
      return
    }
    uploadMutation.mutate({ accountId: Number(selectedAccountId), file })
  }

  const bankAccounts = settingsQuery.data?.bank_accounts ?? []

  return (
    <div>
      <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('bankImport.title')}</h1>

      {/* Upload section */}
      <div className="mb-8 rounded-lg bg-white p-6 shadow-sm">
        <h2 className="mb-4 text-base font-semibold text-gray-700">{t('bankImport.upload')}</h2>
        <form onSubmit={handleUpload} className="space-y-4">
          {/* Bank account select */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('bankImport.selectAccount')}
            </label>
            {settingsQuery.isLoading ? (
              <p className="text-sm text-gray-400">{t('common.loading')}</p>
            ) : (
              <select
                value={selectedAccountId}
                onChange={e => setSelectedAccountId(e.target.value === '' ? '' : Number(e.target.value))}
                className="rounded border border-gray-300 px-3 py-2 text-sm w-64 focus:border-blue-500 focus:outline-none"
              >
                <option value="">{t('bankImport.selectAccount')}</option>
                {bankAccounts.map(acc => (
                  <option key={acc.bank_account_id} value={acc.bank_account_id}>
                    {acc.bank_name} {acc.bank_branch} {acc.account_number}
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* File input */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {t('bankImport.selectFile')}
            </label>
            <input
              ref={fileRef}
              type="file"
              accept=".csv"
              className="text-sm text-gray-700"
            />
          </div>

          {uploadError && (
            <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{uploadError}</p>
          )}
          {uploadSuccess && (
            <p className="rounded bg-green-50 px-3 py-2 text-sm text-green-700">{t('bankImport.success')}</p>
          )}

          <button
            type="submit"
            disabled={uploadMutation.isPending}
            className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {uploadMutation.isPending ? t('common.loading') : t('bankImport.submit')}
          </button>
        </form>
      </div>

      {/* Batch list */}
      <div className="rounded-lg bg-white shadow-sm">
        <div className="p-6 pb-3">
          <h2 className="text-base font-semibold text-gray-700">{t('bankImport.batches')}</h2>
        </div>
        {batchQuery.isLoading && (
          <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
        )}
        {batchQuery.isError && (
          <p className="px-6 py-4 text-sm text-red-600">{batchQuery.error.message}</p>
        )}
        {batchQuery.data && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                <th className="px-6 py-3">ID</th>
                <th className="px-6 py-3">ファイル名</th>
                <th className="px-6 py-3">件数</th>
                <th className="px-6 py-3">ステータス</th>
                <th className="px-6 py-3">取込日</th>
                <th className="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {batchQuery.data.items.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-6 py-4 text-center text-gray-400">
                    {t('common.noData')}
                  </td>
                </tr>
              )}
              {batchQuery.data.items.map((batch, i) => (
                <tr
                  key={batch.bank_import_batch_id}
                  className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                >
                  <td className="px-6 py-3 text-gray-500">{batch.bank_import_batch_id}</td>
                  <td className="px-6 py-3 font-medium text-gray-800">{batch.source_filename}</td>
                  <td className="px-6 py-3 text-gray-700">{batch.row_count}</td>
                  <td className="px-6 py-3">
                    <StatusBadge status={batch.status} />
                  </td>
                  <td className="px-6 py-3 text-gray-600">{formatDate(batch.imported_at)}</td>
                  <td className="px-6 py-3">
                    {batch.status === 'imported' && (
                      <button
                        onClick={() => { setReverseTarget(batch); setReversalReason('') }}
                        className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                      >
                        {t('bankImport.reverse')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Reverse confirm dialog */}
      {reverseTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 className="mb-4 text-base font-semibold text-gray-900">
              {t('bankImport.confirmReverse')}
            </h3>
            <p className="mb-4 text-sm text-gray-600">{reverseTarget.source_filename}</p>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                {t('bankImport.reversalReason')}
              </label>
              <input
                type="text"
                value={reversalReason}
                onChange={e => setReversalReason(e.target.value)}
                className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              />
            </div>
            {reverseMutation.isError && (
              <p className="mb-3 text-sm text-red-600">{reverseMutation.error.message}</p>
            )}
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setReverseTarget(null)}
                className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                {t('common.cancel')}
              </button>
              <button
                onClick={() =>
                  reverseMutation.mutate({ id: reverseTarget.bank_import_batch_id, reason: reversalReason })
                }
                disabled={reverseMutation.isPending || !reversalReason.trim()}
                className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
              >
                {reverseMutation.isPending ? t('common.loading') : t('bankImport.reverse')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
