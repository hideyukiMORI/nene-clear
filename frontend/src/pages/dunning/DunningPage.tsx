import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  listDunningNotices, sendDunningNotice, previewDunningNotice, testSendDunningNotice, listUpstreamInvoices, type DunningStage,
  listDunningPauses, pauseDunningNotice, resumeDunningNotice, dunningNoticesExportPath,
} from '@/shared/api/endpoints'
import { downloadCsv } from '@/shared/lib/download'
import type { UpstreamInvoice } from '@/entities/upstream-invoice'
import { Badge } from '@/shared/ui/badge'
import { Button } from '@/shared/ui/button'
import { Card } from '@hideyukimori/nene2-ui'
import { CardHead } from '@/shared/ui/card'
import { DataTable, TableStateRow, SortableTh, nextSort, Pager } from '@/shared/ui/data-table'
import type { SortState } from '@/shared/ui/data-table'
import { DatePicker } from '@/shared/ui/date-picker'
import { FilterBar, FilterField } from '@/shared/ui/filter-bar'
import { Icon } from '@/shared/ui/icon'
import { Modal } from '@/shared/ui/modal'
import { Notice } from '@/shared/ui/notice'
import { PageHead } from '@/shared/ui/page-head'
import { useTranslation } from '@/shared/i18n/use-translation'
import { useFiscalYearDefault } from '@/entities/user'
import { useRowCursor } from '@/components/keyboard'
import { yen, formatDateTime, daysOverdue } from '@/shared/lib/format'

// ─── Send confirm modal ───
function SendModal({ invoice, onClose }: { invoice: UpstreamInvoice; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [stage, setStage] = useState<DunningStage>('initial')
  const mut = useMutation({
    mutationFn: () => sendDunningNotice(invoice.invoice_id, stage),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-notices'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }); onClose() },
  })
  const preview = useQuery({
    queryKey: ['dunning-preview', invoice.invoice_id, stage],
    queryFn: ({ signal }) => previewDunningNotice(invoice.invoice_id, stage, signal),
  })
  const [testTo, setTestTo] = useState('')
  const [testSent, setTestSent] = useState(false)
  const testMut = useMutation({
    mutationFn: () => testSendDunningNotice(invoice.invoice_id, testTo, stage),
    onSuccess: () => setTestSent(true),
  })
  return (
    <Modal open onClose={onClose} title={t('dunning.confirmSend')} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="primary" disabled={mut.isPending} onClick={() => mut.mutate()}><Icon name="send" />{mut.isPending ? t('common.sending') : t('dunning.send')}</Button></>}
    >
      <div className="kv">
        <div className="kv-row"><span className="k">{t('table.invoice')}</span><span className="v mono">{invoice.invoice_number}</span></div>
        <div className="kv-row"><span className="k">{t('table.outstanding')}</span><span className="v">{yen(invoice.outstanding_cents)}</span></div>
        <div className="kv-row"><span className="k">{t('table.dueDate')}</span><span className="v" style={{ color: 'var(--bad)' }}>{t('dunning.dueElapsed', { date: invoice.due_at ?? '—', days: daysOverdue(invoice.due_at) })}</span></div>
      </div>
      <div className="field" style={{ marginTop: 12 }}>
        <label>{t('dunning.stageLabel')}</label>
        <select className="inp" value={stage} onChange={e => setStage(e.target.value as DunningStage)}>
          <option value="initial">{t('dunning.stage.initial')}</option>
          <option value="reminder">{t('dunning.stage.reminder')}</option>
          <option value="final">{t('dunning.stage.final')}</option>
        </select>
      </div>
      <div className="field" style={{ marginTop: 12 }}>
        <label>{t('dunning.previewLabel')}</label>
        {preview.isLoading && <p className="muted" style={{ fontSize: 12, margin: 0 }}>{t('dunning.previewLoading')}</p>}
        {preview.isError && <Notice variant="warn">{t('dunning.previewUnavailable')}</Notice>}
        {preview.data && (
          <div style={{ border: '1px solid var(--border, #e2e8f0)', borderRadius: 6, padding: 12 }}>
            <div style={{ fontSize: 12, color: 'var(--muted, #64748b)' }}>{t('dunning.previewTo')}: {preview.data.recipient_email}</div>
            <div style={{ fontWeight: 600, marginTop: 4 }}>{preview.data.subject}</div>
            <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit', fontSize: 13, margin: '8px 0 0' }}>{preview.data.body}</pre>
          </div>
        )}
      </div>
      <div className="field" style={{ marginTop: 12 }}>
        <label>{t('dunning.testSendLabel')}</label>
        <div style={{ display: 'flex', gap: 6 }}>
          <input className="inp" style={{ flex: 1 }} type="email" placeholder={t('dunning.testToPlaceholder')} value={testTo} onChange={e => { setTestTo(e.target.value); setTestSent(false) }} />
          <Button variant="ghost" disabled={!testTo.includes('@') || testMut.isPending} onClick={() => testMut.mutate()}>{testMut.isPending ? t('common.sending') : t('dunning.testSend')}</Button>
        </div>
        {testSent && <p className="muted" style={{ fontSize: 12, margin: '4px 0 0', color: 'var(--ok, #16a34a)' }}>{t('dunning.testSent', { to: testTo })}</p>}
        {testMut.isError && <Notice variant="bad">{testMut.error.message}</Notice>}
      </div>
      <Notice variant="info">{t('dunning.sendInfo')}</Notice>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

// ─── Pause modal ───
function PauseModal({ invoiceId, onClose }: { invoiceId: number; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [reason, setReason] = useState('')
  const mut = useMutation({
    mutationFn: () => pauseDunningNotice(invoiceId, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-pauses'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title={t('dunning.confirmPause')} sub={t('dunning.pauseTarget', { id: invoiceId })} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="warn" disabled={!reason.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon name="pause" />{mut.isPending ? t('common.processing') : t('dunning.pauseAction')}</Button></>}
    >
      <div className="field"><label>{t('dunning.pauseReason')}</label><input className="inp" placeholder={t('dunning.pauseReasonPlaceholder')} value={reason} onChange={e => setReason(e.target.value)} /></div>
      <Notice variant="warn">{t('dunning.pauseWarn')}</Notice>
    </Modal>
  )
}

export default function DunningPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [sendTarget, setSendTarget] = useState<UpstreamInvoice | null>(null)
  const [pauseTarget, setPauseTarget] = useState<number | null>(null)

  const invoicesQ = useQuery({
    queryKey: ['upstream-invoices', { status: 'issued,partially_paid,overdue' }],
    queryFn: ({ signal }) => listUpstreamInvoices({ status: 'issued,partially_paid,overdue' }, signal),
  })
  // Current fiscal year (org 決算月) seeds the sent-date range. /me is loaded by
  // AppShell before this page mounts → available synchronously; Clear resets to
  // all. DatePicker uses YYYY/MM/DD, so convert the ISO range.
  const { range: fyRange } = useFiscalYearDefault()
  const fyFrom = fyRange ? fyRange.fromIso.replace(/-/g, '/') : ''
  const fyTo = fyRange ? fyRange.toIso.replace(/-/g, '/') : ''

  const HPAGE = 50
  const [hInvoice, setHInvoice] = useState('')
  const [hEmail, setHEmail] = useState('')
  const [hFrom, setHFrom] = useState(fyFrom)
  const [hTo, setHTo] = useState(fyTo)
  const [hApplied, setHApplied] = useState({ invoice: '', email: '', from: fyFrom, to: fyTo, offset: 0 })
  const [hSort, setHSort] = useState<SortState>({ by: 'sent_at', dir: 'desc' })
  // Export mirrors the history list's current filter (same params, minus paging).
  const noticesFilter = {
    invoiceNumber: hApplied.invoice || undefined,
    recipientEmail: hApplied.email || undefined,
    sentFrom: hApplied.from ? hApplied.from.replace(/\//g, '-') : undefined,
    sentTo: hApplied.to ? hApplied.to.replace(/\//g, '-') : undefined,
    sortBy: hSort.by, sortDir: hSort.dir,
  }
  const noticesQ = useQuery({
    queryKey: ['dunning-notices', hApplied, hSort],
    queryFn: ({ signal }) => listDunningNotices({ ...noticesFilter, limit: HPAGE, offset: hApplied.offset }, signal),
  })
  function hSearch() { setHApplied({ invoice: hInvoice, email: hEmail, from: hFrom, to: hTo, offset: 0 }) }
  function hClear() { setHInvoice(''); setHEmail(''); setHFrom(''); setHTo(''); setHApplied({ invoice: '', email: '', from: '', to: '', offset: 0 }) }
  function hOnSort(col: string) { setHSort(s => nextSort(s, col)); setHApplied(p => ({ ...p, offset: 0 })) }
  const hTotal = noticesQ.data?.total ?? 0
  const pausesQ = useQuery({ queryKey: ['dunning-pauses', { active_only: true }], queryFn: ({ signal }) => listDunningPauses({ active_only: true, limit: 100 }, signal) })

  const resumeMut = useMutation({
    mutationFn: (id: number) => resumeDunningNotice(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-pauses'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }) },
  })

  const activePauses = new Set((pausesQ.data?.items ?? []).map(p => p.invoice_id))

  // Row cursor (j/k) targets the actionable "eligible invoices" list; Enter/o
  // opens the send dialog for the cursored invoice (skipped when paused).
  const eligibleRows = invoicesQ.data?.items ?? []
  const cursor = useRowCursor(eligibleRows.length, (i) => {
    const inv = eligibleRows[i]
    if (inv && !activePauses.has(inv.invoice_id)) setSendTarget(inv)
  })

  return (
    <>
      <PageHead
        title={t('dunning.title')}
        sub={t('dunning.subtitle')}
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv(dunningNoticesExportPath(noticesFilter), 'dunning-notices.csv')}>
            <Icon name="export" />{t('export.csv')}
          </Button>
        }
      />

      {/* Pause banner */}
      {activePauses.size > 0 && (
        <Card pad="none" className="min-w-0" style={{ borderColor: 'var(--warn-line)', background: 'var(--warn-bg)', marginBottom: 20 }}>
          <div style={{ padding: '14px 18px' }}>
            <div className="row" style={{ color: 'var(--warn)', fontWeight: 700, fontSize: 13, marginBottom: 10 }}>
              <Icon name="pause" /> {t('dunning.pausedBanner', { count: activePauses.size })}
            </div>
            <div className="wrapw">
              {(pausesQ.data?.items ?? []).map(p => (
                <span key={p.dunning_pause_id} className="tag-pill">
                  <b className="mono">{t('table.invoice')} #{p.invoice_id}</b>
                  <span className="faint">{p.paused_reason}</span>
                  <Button variant="link" onClick={() => resumeMut.mutate(p.invoice_id)}>
                    <Icon name="refresh" size="sm" />{t('dunning.resume')}
                  </Button>
                </span>
              ))}
            </div>
          </div>
        </Card>
      )}

      {/* Eligible invoices */}
      <Card pad="none" className="min-w-0">
        <CardHead>
          <h2><Icon name="alert" />{t('dunning.eligibleInvoices')}</h2>
          <p>{t('dunning.eligibleSubtitle')}</p>
        </CardHead>
        <DataTable>
          <thead>
            <tr><th>{t('table.invoiceNumber')}</th><th>{t('table.status')}</th><th>{t('table.outstanding')}</th><th>{t('table.dueDate')}</th><th>{t('table.elapsed')}</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={6} loading={invoicesQ.isLoading} empty={invoicesQ.data?.items.length === 0} emptyKey="dunning.noEligible" />
            {invoicesQ.data?.items.map((inv, index) => {
              const paused = activePauses.has(inv.invoice_id)
              const elap = daysOverdue(inv.due_at)
              const daysNum = parseInt(elap)
              return (
                <tr key={inv.invoice_id} data-kbd-row={index} className={[paused ? 'dim' : '', cursor === index ? 'is-cursor' : ''].filter(Boolean).join(' ') || undefined}>
                  <td className="strong mono">{inv.invoice_number}</td>
                  <td>
                    {daysNum > 0 ? <Badge variant="bad" dot>{t('dunning.status.overdue')}</Badge> : <Badge variant="warn" dot>{t('dunning.status.partial')}</Badge>}
                  </td>
                  <td className="num">{yen(inv.outstanding_cents)}</td>
                  <td className="muted">{inv.due_at ?? '—'}</td>
                  <td style={{ color: daysNum > 0 ? (daysNum > 14 ? 'var(--bad)' : 'var(--warn)') : 'var(--muted)', fontWeight: 600 }}>{elap}</td>
                  <td className="row-act">
                    {paused
                      ? <Badge variant="warn"><Icon name="pause" size="sm" />{t('dunning.paused')}</Badge>
                      : <Button variant="primary" size="sm" onClick={() => setSendTarget(inv)}><Icon name="send" size="sm" />{t('dunning.send')}</Button>
                    }
                    {!paused && (
                      <Button variant="ghost-warn" size="sm" onClick={() => setPauseTarget(inv.invoice_id)}>
                        <Icon name="pause" size="sm" />{t('dunning.pause')}
                      </Button>
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </DataTable>
      </Card>

      {/* History */}
      <FilterBar className="mt-x-md">
        <FilterField label={t('table.invoiceNumber')}>
          <div className="inp-icon">
            <Icon name="search" />
            <input className="inp" data-kbd="search" style={{ paddingLeft: 32, width: 150 }} placeholder={t('common.search')} value={hInvoice} onChange={e => setHInvoice(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.recipient')}>
          <div className="inp-icon">
            <Icon name="search" />
            <input className="inp" style={{ paddingLeft: 32, width: 170 }} placeholder={t('common.search')} value={hEmail} onChange={e => setHEmail(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.sentAt')}>
          <div className="range-pair">
            <DatePicker value={hFrom} onChange={setHFrom} />
            <span className="tilde">〜</span>
            <DatePicker value={hTo} onChange={setHTo} />
          </div>
        </FilterField>
        <div className="filter-actions">
          <span className="filter-count">{t('filter.count', { n: hTotal })}</span>
          <Button variant="ghost" size="sm" onClick={hClear}><Icon name="refresh" size="sm" />{t('filter.clear')}</Button>
          <Button variant="primary" size="sm" onClick={hSearch}><Icon name="search" size="sm" />{t('common.search')}</Button>
        </div>
      </FilterBar>

      <Card pad="none" className="min-w-0">
        <CardHead><h2><Icon name="clock" />{t('dunning.history')}</h2></CardHead>
        <DataTable>
          <thead>
            <tr>
              <SortableTh column="invoice_number" sort={hSort} onSort={hOnSort}>{t('table.invoiceNumber')}</SortableTh>
              <SortableTh column="recipient_email" sort={hSort} onSort={hOnSort}>{t('table.recipient')}</SortableTh>
              <SortableTh column="outstanding_cents" sort={hSort} onSort={hOnSort}>{t('table.outstanding')}</SortableTh>
              <SortableTh column="sent_at" sort={hSort} onSort={hOnSort}>{t('table.sentAt')}</SortableTh>
              <SortableTh column="sent_by" sort={hSort} onSort={hOnSort}>{t('table.sender')}</SortableTh>
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={5} loading={noticesQ.isLoading} empty={noticesQ.data?.items.length === 0} />
            {noticesQ.data?.items.map(n => (
              <tr key={n.dunning_notice_id}>
                <td className="strong mono">{n.invoice_number}</td>
                <td className="muted">{n.recipient_email}</td>
                <td className="num">{yen(n.outstanding_at_send_cents)}</td>
                <td className="muted">{formatDateTime(n.sent_at)}</td>
                <td className="muted">#{n.sent_by}</td>
              </tr>
            ))}
          </tbody>
        </DataTable>
        <Pager offset={hApplied.offset} pageSize={HPAGE} total={hTotal} onOffsetChange={off => setHApplied(p => ({ ...p, offset: off }))} />
      </Card>

      {sendTarget && <SendModal invoice={sendTarget} onClose={() => setSendTarget(null)} />}
      {pauseTarget !== null && <PauseModal invoiceId={pauseTarget} onClose={() => setPauseTarget(null)} />}
    </>
  )
}
