import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  listManualReceivables, createManualReceivable, cancelManualReceivable, importManualReceivables,
} from '@/shared/api/endpoints'
import { describeApiError } from '@/shared/api/client'
import type { ManualReceivable, ManualReceivableImportResult } from '@/types'
import { Button } from '@/shared/ui/button'
import { Card } from '@/shared/ui/card'
import { DataTable, TableStateRow } from '@/shared/ui/data-table'
import { Icon } from '@/shared/ui/icon'
import { Modal } from '@/shared/ui/modal'
import { Notice } from '@/shared/ui/notice'
import { PageHead } from '@/shared/ui/page-head'
import { StatusBadge } from '@/shared/ui/status-badge'
import type { StatusMeta } from '@/shared/ui/status-badge'
import { useTranslation } from '@/shared/i18n/use-translation'
import { useRowCursor } from '@/components/keyboard'
import { yen, formatDate } from '@/shared/lib/format'

const STATUS_MAP: Record<ManualReceivable['status'], StatusMeta> = {
  open: { v: 'neut', labelKey: 'manualReceivables.status.open' },
  partially_paid: { v: 'warn', labelKey: 'manualReceivables.status.partiallyPaid' },
  paid: { v: 'ok', labelKey: 'manualReceivables.status.paid' },
  cancelled: { v: 'neut', labelKey: 'manualReceivables.status.cancelled' },
}

/** The "this is a reference, not a tax original" framing required by ADR 0014 (X1). */
function NotOriginalNotice() {
  const { t } = useTranslation()
  return <Notice variant="info">{t('manualReceivables.notOriginal')}</Notice>
}

function CreateModal({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [reference, setReference] = useState('')
  const [clientName, setClientName] = useState('')
  const [totalYen, setTotalYen] = useState('')
  const [email, setEmail] = useState('')
  const [dueAt, setDueAt] = useState('')
  const [error, setError] = useState('')

  const totalCents = totalYen ? Math.round(Number(totalYen) * 100) : 0
  const valid = reference.trim() !== '' && clientName.trim() !== '' && totalCents > 0

  const mut = useMutation({
    mutationFn: () => createManualReceivable({
      reference_number: reference.trim(),
      client_name: clientName.trim(),
      total_cents: totalCents,
      recipient_email: email.trim() || undefined,
      due_at: dueAt || undefined,
    }),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['manual-receivables'] }); onClose() },
    onError: (e: Error) => setError(e.message),
  })

  return (
    <Modal open onClose={onClose} title={t('manualReceivables.create')} sub={t('manualReceivables.createSub')}
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="primary" disabled={!valid || mut.isPending} onClick={() => mut.mutate()}><Icon name="plus" />{mut.isPending ? t('common.saving') : t('manualReceivables.create')}</Button></>}
    >
      <NotOriginalNotice />
      <div className="field">
        <label>{t('manualReceivables.referenceNumber')}</label>
        <input className="inp" value={reference} onChange={e => setReference(e.target.value)} placeholder="INV-2026-001" />
        <span className="hint">{t('manualReceivables.referenceHint')}</span>
      </div>
      <div className="field">
        <label>{t('manualReceivables.clientName')}</label>
        <input className="inp" value={clientName} onChange={e => setClientName(e.target.value)} placeholder={t('manualReceivables.clientNamePlaceholder')} />
      </div>
      <div className="field">
        <label>{t('manualReceivables.total')}</label>
        <input className="inp tnum" type="number" min="1" value={totalYen} onChange={e => setTotalYen(e.target.value)} placeholder="110000" />
      </div>
      <div className="field">
        <label>{t('manualReceivables.dueAt')}</label>
        <input className="inp" type="date" value={dueAt} onChange={e => setDueAt(e.target.value)} />
        <span className="hint">{t('manualReceivables.dueHint')}</span>
      </div>
      <div className="field">
        <label>{t('manualReceivables.recipientEmail')}</label>
        <div className="inp-icon"><Icon name="mail" /><input className="inp" type="email" style={{ paddingLeft: 34 }} value={email} onChange={e => setEmail(e.target.value)} placeholder={t('manualReceivables.emailPlaceholder')} /></div>
        <span className="hint">{t('manualReceivables.emailHint')}</span>
      </div>
      {error && <Notice variant="bad">{error}</Notice>}
    </Modal>
  )
}

function ImportModal({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<ManualReceivableImportResult | null>(null)
  const [error, setError] = useState('')

  const mut = useMutation({
    mutationFn: () => importManualReceivables(file as File),
    onSuccess: (r) => { setResult(r); void qc.invalidateQueries({ queryKey: ['manual-receivables'] }) },
    onError: (e: Error) => setError(describeApiError(e)),
  })

  return (
    <Modal open onClose={onClose} title={t('manualReceivables.import')} sub={t('manualReceivables.importSub')}
      footer={<><Button variant="ghost" onClick={onClose}>{result ? t('common.close') : t('common.cancel')}</Button>{!result && <Button variant="primary" disabled={!file || mut.isPending} onClick={() => mut.mutate()}><Icon name="import" />{mut.isPending ? t('common.processing') : t('manualReceivables.import')}</Button>}</>}
    >
      <NotOriginalNotice />
      <p className="muted" style={{ fontSize: 12.5, lineHeight: 1.8 }}>{t('manualReceivables.importColumns')}</p>
      {!result && (
        <div className="field">
          <input type="file" accept=".csv,text/csv" onChange={e => setFile(e.target.files?.[0] ?? null)} />
        </div>
      )}
      {result && (
        <Notice variant={result.errors.length > 0 ? 'warn' : 'ok'}>
          {t('manualReceivables.importResult', { created: String(result.created), skipped: String(result.skipped), errors: String(result.errors.length) })}
        </Notice>
      )}
      {result && result.errors.length > 0 && (
        <DataTable>
          <thead><tr><th>{t('manualReceivables.csvRow')}</th><th>{t('common.error')}</th></tr></thead>
          <tbody>
            {result.errors.map((e, i) => (
              <tr key={i}><td className="num">{e.row}</td><td className="muted">{e.errors.map(x => `${x.field}: ${x.message}`).join('; ')}</td></tr>
            ))}
          </tbody>
        </DataTable>
      )}
      {error && <Notice variant="bad">{error}</Notice>}
    </Modal>
  )
}

function CancelModal({ receivable, onClose }: { receivable: ManualReceivable; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const mut = useMutation({
    mutationFn: () => cancelManualReceivable(receivable.manual_receivable_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['manual-receivables'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title={t('manualReceivables.confirmCancel')} sub={receivable.reference_number} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="warn" disabled={mut.isPending} onClick={() => mut.mutate()}>{mut.isPending ? t('common.processing') : t('manualReceivables.cancel')}</Button></>}
    >
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function ManualReceivablesPage() {
  const { t } = useTranslation()
  const [showCreate, setShowCreate] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [cancelTarget, setCancelTarget] = useState<ManualReceivable | null>(null)

  const listQ = useQuery({ queryKey: ['manual-receivables'], queryFn: ({ signal }) => listManualReceivables({ limit: 100 }, signal) })
  const rows = listQ.data?.items ?? []
  const cursor = useRowCursor(rows.length, (i) => { const r = rows[i]; if (r && r.status !== 'cancelled') setCancelTarget(r) })

  return (
    <>
      <PageHead
        title={t('manualReceivables.title')}
        sub={t('manualReceivables.subtitle')}
        actions={<>
          <Button variant="ghost" onClick={() => setShowImport(true)}><Icon name="import" />{t('manualReceivables.import')}</Button>
          <Button variant="primary" onClick={() => setShowCreate(true)}><Icon name="plus" />{t('manualReceivables.create')}</Button>
        </>}
      />

      <NotOriginalNotice />

      <Card>
        <DataTable>
          <thead>
            <tr>
              <th>{t('manualReceivables.referenceNumber')}</th>
              <th>{t('manualReceivables.clientName')}</th>
              <th>{t('manualReceivables.total')}</th>
              <th>{t('manualReceivables.outstanding')}</th>
              <th>{t('manualReceivables.dueAt')}</th>
              <th>{t('table.status')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={7} loading={listQ.isLoading} empty={rows.length === 0} />
            {rows.map((r, index) => (
              <tr key={r.manual_receivable_id} data-kbd-row={index} className={cursor === index ? 'is-cursor' : undefined}>
                <td className="strong">{r.reference_number}</td>
                <td>{r.client_name}</td>
                <td className="num">{yen(r.total_cents)}</td>
                <td className="num">{yen(r.outstanding_cents)}</td>
                <td className="muted">{r.due_at ? formatDate(r.due_at) : '—'}</td>
                <td><StatusBadge map={STATUS_MAP} value={r.status} dot /></td>
                <td className="row-act">
                  {r.status !== 'cancelled' && r.status !== 'paid' && (
                    <Button variant="ghost-danger" size="sm" onClick={() => setCancelTarget(r)}>{t('manualReceivables.cancel')}</Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      {showCreate && <CreateModal onClose={() => setShowCreate(false)} />}
      {showImport && <ImportModal onClose={() => setShowImport(false)} />}
      {cancelTarget && <CancelModal receivable={cancelTarget} onClose={() => setCancelTarget(null)} />}
    </>
  )
}
