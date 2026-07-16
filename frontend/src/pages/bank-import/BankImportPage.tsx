import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listBankImportBatches, importBankCsv, reverseBankImportBatch, getClearSettings } from '@/api/endpoints'
import { describeApiError } from '@/shared/api/client'
import type { BankImportBatch } from '@/types'
import { Icon, StatusBadge, Button, Card, CardHead, CardBody, DataTable, TableStateRow, Notice, Modal, PageHead, FilterBar, FilterField, DatePicker, SortableTh, nextSort, Pager } from '@/components/ui'
import type { StatusMeta, SortState } from '@/components/ui'
import { useTranslation } from '@/shared/i18n/use-translation'
import { useRowCursor } from '@/components/keyboard'
import { formatDate } from '@/shared/lib/format'

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

  const PAGE = 50
  const [fileName, setFileName] = useState('')
  const [bStatus, setBStatus] = useState('')
  const [rowMin, setRowMin] = useState('')
  const [rowMax, setRowMax] = useState('')
  const [impFrom, setImpFrom] = useState('')
  const [impTo, setImpTo] = useState('')
  const [applied, setApplied] = useState({ fileName: '', status: '', rowMin: '', rowMax: '', impFrom: '', impTo: '', offset: 0 })
  const [sort, setSort] = useState<SortState>({ by: 'id', dir: 'desc' })

  const settingsQ = useQuery({ queryKey: ['clear-settings'], queryFn: ({ signal }) => getClearSettings(signal) })
  const batchQ = useQuery({
    queryKey: ['bank-import-batches', applied, sort],
    queryFn: ({ signal }) => listBankImportBatches({
      sourceFilename: applied.fileName || undefined,
      status: applied.status || undefined,
      rowCountMin: applied.rowMin ? Number(applied.rowMin) : undefined,
      rowCountMax: applied.rowMax ? Number(applied.rowMax) : undefined,
      importedFrom: applied.impFrom ? applied.impFrom.replace(/\//g, '-') : undefined,
      importedTo: applied.impTo ? applied.impTo.replace(/\//g, '-') : undefined,
      sortBy: sort.by, sortDir: sort.dir, limit: PAGE, offset: applied.offset,
    }, signal),
  })
  function searchBatches() { setApplied({ fileName, status: bStatus, rowMin, rowMax, impFrom, impTo, offset: 0 }) }
  function clearBatches() { setFileName(''); setBStatus(''); setRowMin(''); setRowMax(''); setImpFrom(''); setImpTo(''); setApplied({ fileName: '', status: '', rowMin: '', rowMax: '', impFrom: '', impTo: '', offset: 0 }) }
  function onSortBatches(col: string) { setSort(s => nextSort(s, col)); setApplied(p => ({ ...p, offset: 0 })) }
  const batchTotal = batchQ.data?.total ?? 0

  // Row cursor (j/k); Enter/o opens the reverse dialog for the cursored batch.
  const batchRows = batchQ.data?.items ?? []
  const cursor = useRowCursor(batchRows.length, (i) => {
    const b = batchRows[i]
    if (b && b.status === 'imported') setReverseTarget(b)
  })

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
    onError: (err: Error) => setUploadMsg({ ok: false, text: describeApiError(err) }),
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

      <FilterBar>
        <FilterField label={t('table.fileName')}>
          <div className="inp-icon">
            <Icon name="search" />
            <input className="inp" data-kbd="search" style={{ paddingLeft: 32, width: 160 }} placeholder={t('common.search')} value={fileName} onChange={e => setFileName(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.status')}>
          <select className="inp" value={bStatus} onChange={e => setBStatus(e.target.value)}>
            <option value="">{t('filter.all')}</option>
            <option value="imported">{t('bankImport.status.imported')}</option>
            <option value="reversed">{t('bankImport.status.reversed')}</option>
          </select>
        </FilterField>
        <FilterField label={t('table.rowCount')}>
          <div className="range-pair">
            <input className="inp tnum" type="number" value={rowMin} onChange={e => setRowMin(e.target.value)} />
            <span className="tilde">〜</span>
            <input className="inp tnum" type="number" value={rowMax} onChange={e => setRowMax(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.importedAt')}>
          <div className="range-pair">
            <DatePicker value={impFrom} onChange={setImpFrom} />
            <span className="tilde">〜</span>
            <DatePicker value={impTo} onChange={setImpTo} />
          </div>
        </FilterField>
        <div className="filter-actions">
          <span className="filter-count">{t('filter.count', { n: batchTotal })}</span>
          <Button variant="ghost" size="sm" onClick={clearBatches}><Icon name="refresh" size="sm" />{t('filter.clear')}</Button>
          <Button variant="primary" size="sm" onClick={searchBatches}><Icon name="search" size="sm" />{t('common.search')}</Button>
        </div>
      </FilterBar>

      <Card>
        <CardHead><h2><Icon name="clock" />{t('bankImport.batches')}</h2></CardHead>
        <DataTable>
          <thead>
            <tr>
              <SortableTh column="id" sort={sort} onSort={onSortBatches}>{t('table.id')}</SortableTh>
              <SortableTh column="source_filename" sort={sort} onSort={onSortBatches}>{t('table.fileName')}</SortableTh>
              <SortableTh column="row_count" sort={sort} onSort={onSortBatches}>{t('table.rowCount')}</SortableTh>
              <SortableTh column="status" sort={sort} onSort={onSortBatches}>{t('table.status')}</SortableTh>
              <SortableTh column="imported_at" sort={sort} onSort={onSortBatches}>{t('table.importedAt')}</SortableTh>
              <th />
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={6} loading={batchQ.isLoading} empty={batchQ.data?.items.length === 0} />
            {batchQ.data?.items.map((b, index) => (
              <tr key={b.bank_import_batch_id} data-kbd-row={index} className={[b.status === 'reversed' ? 'dim' : '', cursor === index ? 'is-cursor' : ''].filter(Boolean).join(' ') || undefined}>
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
        <Pager offset={applied.offset} pageSize={PAGE} total={batchTotal} onOffsetChange={off => setApplied(p => ({ ...p, offset: off }))} />
      </Card>

      {reverseTarget && <ReverseModal batch={reverseTarget} onClose={() => setReverseTarget(null)} />}
    </>
  )
}
