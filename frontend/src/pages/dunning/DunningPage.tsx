import { useState, useEffect, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  listDunningNotices, sendDunningNotice, listUpstreamInvoices,
  listDunningPauses, pauseDunningNotice, resumeDunningNotice, downloadCsv, dunningNoticesExportPath,
} from '@/api/endpoints'
import type { UpstreamInvoice } from '@/types'
import { Icon, Badge, Button, Card, CardHead, DataTable, TableStateRow, Modal, Notice, PageHead, FilterBar, FilterField, DatePicker, SortableTh, nextSort, Pager } from '@/components/ui'
import type { SortState } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import { useFiscalYearDefault } from '@/hooks/useFiscalYearDefault'
import { useRowCursor } from '@/components/keyboard'
import { yen, formatDateTime, daysOverdue } from '@/utils/format'

// ─── Send confirm modal ───
function SendModal({ invoice, onClose }: { invoice: UpstreamInvoice; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const mut = useMutation({
    mutationFn: () => sendDunningNotice(invoice.invoice_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-notices'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title={t('dunning.confirmSend')} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="primary" disabled={mut.isPending} onClick={() => mut.mutate()}><Icon name="send" />{mut.isPending ? t('common.sending') : t('dunning.send')}</Button></>}
    >
      <div className="kv">
        <div className="kv-row"><span className="k">{t('table.invoice')}</span><span className="v mono">{invoice.invoice_number}</span></div>
        <div className="kv-row"><span className="k">{t('table.outstanding')}</span><span className="v">{yen(invoice.outstanding_cents)}</span></div>
        <div className="kv-row"><span className="k">{t('table.dueDate')}</span><span className="v" style={{ color: 'var(--bad)' }}>{t('dunning.dueElapsed', { date: invoice.due_at, days: daysOverdue(invoice.due_at) })}</span></div>
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
  const HPAGE = 50
  const [hInvoice, setHInvoice] = useState('')
  const [hEmail, setHEmail] = useState('')
  const [hFrom, setHFrom] = useState('')
  const [hTo, setHTo] = useState('')
  const [hApplied, setHApplied] = useState({ invoice: '', email: '', from: '', to: '', offset: 0 })
  const [hSort, setHSort] = useState<SortState>({ by: 'sent_at', dir: 'desc' })

  // Default the sent-date range to the current fiscal year (org 決算月) once /me
  // resolves; applied once, Clear resets to all. DatePicker uses YYYY/MM/DD.
  const { range: fyRange, settled: fySettled } = useFiscalYearDefault()
  const fyApplied = useRef(false)
  useEffect(() => {
    if (!fySettled || fyApplied.current) return
    fyApplied.current = true
    if (!fyRange) return
    const from = fyRange.fromIso.replace(/-/g, '/')
    const to = fyRange.toIso.replace(/-/g, '/')
    // One-time sync of the async org fiscal-year default into editable filter state.
    /* eslint-disable react-hooks/set-state-in-effect */
    setHFrom(from)
    setHTo(to)
    setHApplied(p => ({ ...p, from, to, offset: 0 }))
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [fySettled, fyRange])
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
        <div className="card" style={{ borderColor: 'var(--warn-line)', background: 'var(--warn-bg)', marginBottom: 20 }}>
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
        </div>
      )}

      {/* Eligible invoices */}
      <Card>
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
                    {inv.status === 'overdue' ? <Badge variant="bad" dot>{t('dunning.status.overdue')}</Badge> : <Badge variant="warn" dot>{t('dunning.status.partial')}</Badge>}
                  </td>
                  <td className="num">{yen(inv.outstanding_cents)}</td>
                  <td className="muted">{inv.due_at}</td>
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
      <FilterBar>
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

      <Card>
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
