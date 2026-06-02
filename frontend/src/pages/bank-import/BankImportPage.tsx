import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listBankImportBatches, importBankCsv, reverseBankImportBatch, getClearSettings } from '@/api/endpoints'
import type { BankImportBatch } from '@/types'
import { Icon, StatusBadge, Button, Card, CardHead, CardBody, DataTable, TableStateRow, Notice, Modal, PageHead } from '@/components/ui'
import type { StatusMeta } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import { formatDate } from '@/utils/format'

const BATCH_STATUS: Record<BankImportBatch['status'], StatusMeta> = {
  imported: { v: 'ok',   labelKey: 'bankImport.status.imported' },
  reversed: { v: 'neut', labelKey: 'bankImport.status.reversed' },
}

interface ReverseModalProps { batch: BankImportBatch; onClose: () => void }
function ReverseModal({ batch, onClose }: ReverseModalProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [reason, setReason] = useState('')
  const mut = useMutation({
    mutationFn: () => reverseBankImportBatch(batch.bank_import_batch_id, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['bank-import-batches'] }); onClose() },
  })
  return (
    <Modal
      open
      onClose={onClose}
      title={t('bankImport.confirmReverse')}
      sub={batch.source_filename}
      size="narrow"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="danger" disabled={!reason.trim() || mut.isPending} onClick={() => mut.mutate()}>
            <Icon name="trash" size="sm" />{mut.isPending ? t('common.processing') : t('bankImport.reverse')}
          </Button>
        </>
      }
    >
      <div className="field">
        <label>{t('bankImport.reversalReason')}</label>
        <input className="inp" value={reason} onChange={e => setReason(e.target.value)} />
      </div>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function BankImportPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const [accountId, setAccountId] = useState<number | ''>('')
  const [reverseTarget, setReverseTarget] = useState<BankImportBatch | null>(null)
  const [uploadMsg, setUploadMsg] = useState<{ ok: boolean; text: string } | null>(null)

  const settingsQ = useQuery({ queryKey: ['clear-settings'], queryFn: ({ signal }) => getClearSettings(signal) })
  const batchQ = useQuery({ queryKey: ['bank-import-batches'], queryFn: ({ signal }) => listBankImportBatches({ limit: 50 }, signal) })

  const uploadMut = useMutation({
    mutationFn: ({ id, file }: { id: number; file: File }) => importBankCsv(id, file),
    onSuccess: (data: unknown) => {
      const rows = (data as { row_count?: number })?.row_count ?? '?'
      setUploadMsg({ ok: true, text: t('bankImport.success', { count: rows }) })
      setAccountId('')
      if (fileRef.current) fileRef.current.value = ''
      void qc.invalidateQueries({ queryKey: ['bank-import-batches'] })
      void qc.invalidateQueries({ queryKey: ['bank-transactions'] })
    },
    onError: (err: Error) => setUploadMsg({ ok: false, text: err.message }),
  })

  function handleUpload(e: React.FormEvent) {
    e.preventDefault()
    setUploadMsg(null)
    const file = fileRef.current?.files?.[0]
    if (!file || accountId === '') { setUploadMsg({ ok: false, text: t('bankImport.selectError') }); return }
    uploadMut.mutate({ id: Number(accountId), file })
  }

  const accounts = settingsQ.data?.bank_accounts ?? []

  return (
    <>
      <PageHead title={t('bankImport.title')} sub={t('bankImport.subtitle')} />

      <Card style={{ maxWidth: 760 }}>
        <CardHead><h2><Icon name="cloud-up" />{t('bankImport.upload')}</h2></CardHead>
        <CardBody className="stack">
          <form onSubmit={handleUpload} className="stack">
            <div className="field" style={{ maxWidth: 380 }}>
              <label>{t('bankImport.selectAccount')}</label>
              <select className="inp" value={accountId} onChange={e => setAccountId(e.target.value === '' ? '' : Number(e.target.value))}>
                <option value="">{t('bankImport.selectAccount')}</option>
                {accounts.map(a => (
                  <option key={a.bank_account_id} value={a.bank_account_id}>
                    {a.bank_name} {a.bank_branch} {t(a.account_type === 'ordinary' ? 'settings.accountType.ordinary' : 'settings.accountType.current')} {a.account_number}
                  </option>
                ))}
              </select>
            </div>
            <div className="field">
              <label>{t('bankImport.file')}</label>
              <div className="dropzone">
                <span className="dz-ic"><Icon name="file" /></span>
                <div style={{ flex: 1 }}>
                  <input ref={fileRef} type="file" accept=".csv" style={{ fontSize: 13 }} />
                  <small style={{ display: 'block', marginTop: 4 }}>{t('bankImport.dropHint')}</small>
                </div>
              </div>
            </div>
            {uploadMsg && <Notice variant={uploadMsg.ok ? 'ok' : 'bad'}>{uploadMsg.text}</Notice>}
            <div className="row">
              <Button variant="primary" type="submit" disabled={uploadMut.isPending}>
                <Icon name="import" />{uploadMut.isPending ? t('common.importing') : t('bankImport.submit')}
              </Button>
              <span className="faint" style={{ fontSize: 12 }}>{t('bankImport.dedupeHint')}</span>
            </div>
          </form>
        </CardBody>
      </Card>

      <Card>
        <CardHead><h2><Icon name="clock" />{t('bankImport.batches')}</h2></CardHead>
        <DataTable>
          <thead>
            <tr><th>{t('table.id')}</th><th>{t('table.fileName')}</th><th>{t('table.rowCount')}</th><th>{t('table.status')}</th><th>{t('table.importedAt')}</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={6} loading={batchQ.isLoading} empty={batchQ.data?.items.length === 0} />
            {batchQ.data?.items.map(b => (
              <tr key={b.bank_import_batch_id} className={b.status === 'reversed' ? 'dim' : ''}>
                <td className="muted">{b.bank_import_batch_id}</td>
                <td className="strong mono">{b.source_filename}</td>
                <td className="num">{b.row_count}</td>
                <td><StatusBadge map={BATCH_STATUS} value={b.status} dot /></td>
                <td className="muted">{formatDate(b.imported_at)}</td>
                <td className="row-act">
                  {b.status === 'imported' && (
                    <Button variant="ghost-danger" size="sm" onClick={() => setReverseTarget(b)}>
                      <Icon name="trash" size="sm" />{t('bankImport.reverse')}
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      {reverseTarget && <ReverseModal batch={reverseTarget} onClose={() => setReverseTarget(null)} />}
    </>
  )
}
