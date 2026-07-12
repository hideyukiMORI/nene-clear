import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  listUnmatchedTransactions, listReconciliations,
  confirmMatch, reverseReconciliation, proposeMatch, downloadCsv, reconciliationsExportPath,
} from '@/api/endpoints'
import type { BankTransaction, Reconciliation } from '@/types'
import type { AllocationInput, MatchSuggestion } from '@/api/endpoints'
import { describeApiError } from '@/api/client'
import { Icon, Badge, StatusBadge, Button, Card, DataTable, TableStateRow, Modal, Notice, Tabs, PageHead, InfoDot, FilterBar, FilterField, DatePicker, SortableTh, nextSort, Pager } from '@/components/ui'
import type { SortState } from '@/components/ui'
import type { StatusMeta } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import { useFiscalYearDefault } from '@/hooks/useFiscalYearDefault'
import { useRowCursor } from '@/components/keyboard'
import { yen, formatDate } from '@/utils/format'

const RECON_STATUS: Record<Reconciliation['status'], StatusMeta> = {
  confirmed: { v: 'ok',   labelKey: 'reconciliation.status.confirmed' },
  reversed:  { v: 'neut', labelKey: 'reconciliation.status.reversed' },
}

// ─── Confirm match modal ───
function ConfirmModal({ tx, onClose }: { tx: BankTransaction; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [allocs, setAllocs] = useState<AllocationInput[]>([{ source: 'invoice_upstream', invoice_id: 0, amount_cents: 0 }])
  const [reasonCode, setReasonCode] = useState('')
  const [formError, setFormError] = useState('')

  const suggestQ = useQuery({
    queryKey: ['propose-match', tx.bank_transaction_id],
    queryFn: () => proposeMatch(tx.bank_transaction_id),
    retry: false,
  })

  const allocTargeted = (a: AllocationInput): boolean =>
    a.source === 'manual' ? (a.manual_receivable_id ?? 0) > 0 : (a.invoice_id ?? 0) > 0
  const validAllocs = allocs.filter(a => allocTargeted(a) && a.amount_cents > 0)

  const confirmMut = useMutation({
    mutationFn: () => confirmMatch(tx.bank_transaction_id, validAllocs, reasonCode || undefined),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['bank-transactions', 'unmatched'] })
      void qc.invalidateQueries({ queryKey: ['reconciliations'] })
      onClose()
    },
  })

  // Validate in the browser first so the operator gets a specific, localized
  // reason (the server's generic 422 "Validation Failed" only carries English
  // field messages). Per-row issues are surfaced too: a row needs both a
  // positive invoice ID and a positive amount.
  function submit() {
    if (validAllocs.length === 0) {
      const partial = allocs.some(a => allocTargeted(a) || a.amount_cents > 0)
      setFormError(partial ? t('reconciliation.error.incompleteAllocation') : t('reconciliation.error.noAllocation'))
      return
    }
    setFormError('')
    confirmMut.mutate()
  }

  function applySuggestion(s: MatchSuggestion) {
    setAllocs([s.source === 'manual'
      ? { source: 'manual', manual_receivable_id: s.manual_receivable_id ?? 0, amount_cents: s.outstanding_cents }
      : { source: 'invoice_upstream', invoice_id: s.invoice_id ?? 0, amount_cents: s.outstanding_cents }])
  }

  function updateAlloc(i: number, field: keyof AllocationInput, raw: string) {
    setAllocs(prev => prev.map((a, idx) => idx === i ? { ...a, [field]: field === 'amount_cents' ? Math.round(Number(raw) * 100) : Number(raw) } : a))
  }

  const total = allocs.reduce((s, a) => s + a.amount_cents, 0)

  return (
    <Modal
      open
      onClose={onClose}
      onSubmit={submit}
      title={t('reconciliation.confirm')}
      sub={`${tx.counterparty_text} — ${yen(tx.amount_cents)}（${tx.value_date}）`}
      size="wide"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="primary" type="submit" disabled={confirmMut.isPending}>
            <Icon name="check" />{confirmMut.isPending ? t('common.processing') : t('reconciliation.confirm')}
          </Button>
        </>
      }
    >
      {/* Suggestions */}
      <div className="sugg">
        <div className="sugg-h"><Icon name="reconcile" size="sm" />{t('reconciliation.suggestions')}</div>
        {suggestQ.isLoading && <p className="muted" style={{ fontSize: 12, margin: 0 }}>{t('reconciliation.searchingSuggestions')}</p>}
        {suggestQ.data?.upstream_unavailable && (
          <Notice variant="warn">{t('reconciliation.upstreamUnavailable')}</Notice>
        )}
        {suggestQ.data?.suggestions.length === 0 && (
          <p className="muted" style={{ fontSize: 12, margin: 0 }}>
            {t('reconciliation.noSuggestions')}{' '}
            <Link to="/admin/manual-receivables">{t('reconciliation.noSuggestionsCta')}</Link>
          </p>
        )}
        {suggestQ.data?.suggestions.map((s, i) => (
          <div key={`${s.source}-${s.invoice_id ?? s.manual_receivable_id}-${i}`} className="sugg-row">
            <Badge variant={s.source === 'manual' ? 'neut' : 'info'}>{t(s.source === 'manual' ? 'reconciliation.source.manual' : 'reconciliation.source.invoice')}</Badge>
            <span className="iv mono">{s.invoice_number ?? s.reference_number}</span>
            <span className="amt">{yen(s.outstanding_cents)}</span>
            <Button variant="primary" size="sm" onClick={() => applySuggestion(s)}>{t('reconciliation.useSuggestion')}</Button>
          </div>
        ))}
      </div>

      {/* Allocations */}
      <div>
        <div className="lbl" style={{ marginBottom: 8 }}>{t('reconciliation.allocation')}</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {allocs.map((a, i) => (
            <div key={i} className="alloc-row">
              {a.source === 'manual' ? (
                <div className="field"><label style={{ fontSize: 11 }}>{t('reconciliation.target')}</label>
                  <input className="inp" readOnly value={`${t('reconciliation.source.manual')} #${a.manual_receivable_id ?? ''}`} />
                </div>
              ) : (
                <div className="field"><label style={{ fontSize: 11 }}>{t('reconciliation.invoiceId')}</label>
                  <input className="inp tnum" type="number" data-autofocus={i === 0 ? true : undefined} value={a.invoice_id || ''} onChange={e => updateAlloc(i, 'invoice_id', e.target.value)} />
                </div>
              )}
              <div className="field"><label style={{ fontSize: 11 }}>{t('reconciliation.allocationAmount')}</label>
                <input className="inp tnum" type="number" value={a.amount_cents > 0 ? a.amount_cents / 100 : ''} onChange={e => updateAlloc(i, 'amount_cents', e.target.value)} />
              </div>
              {allocs.length > 1 && (
                <Button variant="ghost" size="sm" style={{ height: 36 }} onClick={() => setAllocs(p => p.filter((_, j) => j !== i))}>
                  <Icon name="x" size="sm" />
                </Button>
              )}
            </div>
          ))}
          <Button variant="link" onClick={() => setAllocs(p => [...p, { source: 'invoice_upstream', invoice_id: 0, amount_cents: 0 }])}>
            <Icon name="plus" size="sm" />{t('reconciliation.addAllocation')}
          </Button>
        </div>
      </div>

      <div className="summary-line">
        <span>{t('reconciliation.allocationTotal')}</span>
        <b className="tnum">{yen(total)} / {yen(tx.amount_cents)}</b>
      </div>

      <div className="field">
        <label>{t('reconciliation.reasonCode')}</label>
        <input className="inp" placeholder={t('reconciliation.reasonCodePlaceholder')} value={reasonCode} onChange={e => setReasonCode(e.target.value)} />
      </div>
      {(formError || confirmMut.isError) && (
        <Notice variant="bad">{formError || describeApiError(confirmMut.error)}</Notice>
      )}
    </Modal>
  )
}

// ─── Reverse modal ───
function ReverseModal({ recon, onClose }: { recon: Reconciliation; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [reason, setReason] = useState('')
  const mut = useMutation({
    mutationFn: () => reverseReconciliation(recon.payment_reconciliation_id, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['reconciliations'] }); void qc.invalidateQueries({ queryKey: ['bank-transactions'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title={t('reconciliation.confirmReverse')} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="danger" disabled={!reason.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon name="refresh" size="sm" />{mut.isPending ? t('common.processing') : t('reconciliation.reverse')}</Button></>}
    >
      <div className="field"><label>{t('reconciliation.reversalReason')}</label><input className="inp" value={reason} onChange={e => setReason(e.target.value)} /></div>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function ReconciliationPage() {
  const { t } = useTranslation()
  const [tab, setTab] = useState('unmatched')
  const [matchTarget, setMatchTarget] = useState<BankTransaction | null>(null)
  const [reverseTarget, setReverseTarget] = useState<Reconciliation | null>(null)

  // Unmatched tab filter/sort/pager
  const UPAGE = 50
  const [uFrom, setUFrom] = useState('')
  const [uTo, setUTo] = useState('')
  const [uAmountMin, setUAmountMin] = useState('')
  const [uAmountMax, setUAmountMax] = useState('')
  const [uCounterparty, setUCounterparty] = useState('')
  const [uApplied, setUApplied] = useState({ from: '', to: '', amountMin: '', amountMax: '', counterparty: '', offset: 0 })
  const [uSort, setUSort] = useState<SortState>({ by: 'value_date', dir: 'desc' })
  const unmatchedQ = useQuery({
    queryKey: ['bank-transactions', 'unmatched', uApplied, uSort],
    queryFn: ({ signal }) => listUnmatchedTransactions({
      valueDateFrom: uApplied.from ? uApplied.from.replace(/\//g, '-') : undefined,
      valueDateTo: uApplied.to ? uApplied.to.replace(/\//g, '-') : undefined,
      amountMinCents: uApplied.amountMin ? Math.round(Number(uApplied.amountMin) * 100) : undefined,
      amountMaxCents: uApplied.amountMax ? Math.round(Number(uApplied.amountMax) * 100) : undefined,
      counterparty: uApplied.counterparty || undefined,
      sortBy: uSort.by, sortDir: uSort.dir, limit: UPAGE, offset: uApplied.offset,
    }, signal),
    enabled: tab === 'unmatched',
  })
  function uSearch() { setUApplied({ from: uFrom, to: uTo, amountMin: uAmountMin, amountMax: uAmountMax, counterparty: uCounterparty, offset: 0 }) }
  function uClear() { setUFrom(''); setUTo(''); setUAmountMin(''); setUAmountMax(''); setUCounterparty(''); setUApplied({ from: '', to: '', amountMin: '', amountMax: '', counterparty: '', offset: 0 }) }
  function uOnSort(col: string) { setUSort(s => nextSort(s, col)); setUApplied(p => ({ ...p, offset: 0 })) }
  const uTotal = unmatchedQ.data?.total ?? 0

  // Current fiscal year (org 決算月) seeds the confirmed-date range. /me is
  // loaded by AppShell before this page mounts → available synchronously here;
  // Clear resets to all. DatePicker uses YYYY/MM/DD, so convert the ISO range.
  const { range: fyRange } = useFiscalYearDefault()
  const fyFrom = fyRange ? fyRange.fromIso.replace(/-/g, '/') : ''
  const fyTo = fyRange ? fyRange.toIso.replace(/-/g, '/') : ''

  const HPAGE = 50
  const [hStatus, setHStatus] = useState('')
  const [hInvoice, setHInvoice] = useState('')
  const [hFrom, setHFrom] = useState(fyFrom)
  const [hTo, setHTo] = useState(fyTo)
  const [hApplied, setHApplied] = useState({ status: '', invoice: '', from: fyFrom, to: fyTo, offset: 0 })
  const [hSort, setHSort] = useState<SortState>({ by: 'id', dir: 'desc' })

  // Export mirrors the history list's current filter (same params, minus paging).
  const reconFilter = {
    status: hApplied.status || undefined,
    invoiceId: hApplied.invoice || undefined,
    confirmedFrom: hApplied.from ? hApplied.from.replace(/\//g, '-') : undefined,
    confirmedTo: hApplied.to ? hApplied.to.replace(/\//g, '-') : undefined,
    sortBy: hSort.by, sortDir: hSort.dir,
  }
  const reconciliationsQ = useQuery({
    queryKey: ['reconciliations', hApplied, hSort],
    queryFn: ({ signal }) => listReconciliations({ ...reconFilter, limit: HPAGE, offset: hApplied.offset }, signal),
    enabled: tab === 'history',
  })
  function hSearch() { setHApplied({ status: hStatus, invoice: hInvoice, from: hFrom, to: hTo, offset: 0 }) }
  function hClear() { setHStatus(''); setHInvoice(''); setHFrom(''); setHTo(''); setHApplied({ status: '', invoice: '', from: '', to: '', offset: 0 }) }
  function hOnSort(col: string) { setHSort(s => nextSort(s, col)); setHApplied(p => ({ ...p, offset: 0 })) }
  const hTotal = reconciliationsQ.data?.total ?? 0

  // Row cursor (j/k) for the active tab; Enter/o opens the row's primary action.
  const unmatchedRows = unmatchedQ.data?.items ?? []
  const historyRows = reconciliationsQ.data?.items ?? []
  const cursor = useRowCursor(tab === 'unmatched' ? unmatchedRows.length : historyRows.length, (i) => {
    if (tab === 'unmatched') {
      const tx = unmatchedRows[i]
      if (tx) setMatchTarget(tx)
    } else {
      const r = historyRows[i]
      if (r && r.status === 'confirmed') setReverseTarget(r)
    }
  })

  return (
    <>
      <PageHead
        title={t('reconciliation.title')}
        sub={t('reconciliation.subtitle')}
        info={<InfoDot label={t('glossary.label')} entries={[
          { term: t('glossary.reconcile.term'), def: t('glossary.reconcile.def') },
          { term: t('glossary.match.term'), def: t('glossary.match.def') },
        ]} />}
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv(reconciliationsExportPath(reconFilter), 'reconciliations.csv')}>
            <Icon name="export" />{t('export.csv')}
          </Button>
        }
      />

      <Tabs
        tabs={[
          { key: 'unmatched', label: <><Icon name="reconcile" size="sm" />{t('reconciliation.tab.unmatched')}</> },
          { key: 'history', label: <><Icon name="clock" size="sm" />{t('reconciliation.tab.history')}</> },
        ]}
        active={tab}
        onChange={setTab}
      />

      {tab === 'unmatched' && (
        <>
        <FilterBar>
          <FilterField label={t('table.valueDate')}>
            <div className="range-pair">
              <DatePicker value={uFrom} onChange={setUFrom} />
              <span className="tilde">〜</span>
              <DatePicker value={uTo} onChange={setUTo} />
            </div>
          </FilterField>
          <FilterField label={t('bankTransaction.amountFrom')}>
            <input className="inp tnum" type="number" placeholder="0" style={{ width: 120 }} value={uAmountMin} onChange={e => setUAmountMin(e.target.value)} />
          </FilterField>
          <FilterField label={t('bankTransaction.amountTo')}>
            <input className="inp tnum" type="number" placeholder="—" style={{ width: 120 }} value={uAmountMax} onChange={e => setUAmountMax(e.target.value)} />
          </FilterField>
          <FilterField label={t('table.counterparty')}>
            <div className="inp-icon">
              <Icon name="search" />
              <input className="inp" data-kbd="search" placeholder={t('common.search')} style={{ paddingLeft: 32 }} value={uCounterparty} onChange={e => setUCounterparty(e.target.value)} />
            </div>
          </FilterField>
          <div className="filter-actions">
            <span className="filter-count">{t('filter.count', { n: uTotal })}</span>
            <Button variant="ghost" size="sm" onClick={uClear}><Icon name="refresh" size="sm" />{t('filter.clear')}</Button>
            <Button variant="primary" size="sm" onClick={uSearch}><Icon name="search" size="sm" />{t('common.search')}</Button>
          </div>
        </FilterBar>
        <Card>
          <DataTable>
            <thead>
              <tr>
                <SortableTh column="value_date" sort={uSort} onSort={uOnSort}>{t('table.valueDate')}</SortableTh>
                <SortableTh column="amount_cents" sort={uSort} onSort={uOnSort}>{t('table.amount')}</SortableTh>
                <SortableTh column="counterparty_text" sort={uSort} onSort={uOnSort}>{t('table.counterparty')}</SortableTh>
                <th>{t('table.matchCandidates')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={5} loading={unmatchedQ.isLoading} empty={unmatchedQ.data?.items.length === 0} emptyKey="reconciliation.noUnmatched" />
              {unmatchedQ.data?.items.map((tx, index) => (
                <tr key={tx.bank_transaction_id} data-kbd-row={index} className={cursor === index ? 'is-cursor' : undefined}>
                  <td className="muted">{tx.value_date}</td>
                  <td className="num">{yen(tx.amount_cents)}</td>
                  <td className="strong">{tx.counterparty_text}</td>
                  <td><Badge variant="info">{t('reconciliation.hasCandidates')}</Badge></td>
                  <td className="row-act">
                    <Button variant="primary" size="sm" onClick={() => setMatchTarget(tx)}>
                      <Icon name="check" size="sm" />{t('reconciliation.confirm')}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
          <Pager offset={uApplied.offset} pageSize={UPAGE} total={uTotal} onOffsetChange={off => setUApplied(p => ({ ...p, offset: off }))} />
        </Card>
        </>
      )}

      {tab === 'history' && (
        <>
        <FilterBar>
          <FilterField label={t('table.status')}>
            <select className="inp" value={hStatus} onChange={e => setHStatus(e.target.value)}>
              <option value="">{t('filter.all')}</option>
              <option value="confirmed">{t('reconciliation.status.confirmed')}</option>
              <option value="reversed">{t('reconciliation.status.reversed')}</option>
            </select>
          </FilterField>
          <FilterField label={t('reconciliation.invoiceId')}>
            <input className="inp tnum" data-kbd="search" type="number" placeholder="#" style={{ width: 110 }} value={hInvoice} onChange={e => setHInvoice(e.target.value)} />
          </FilterField>
          <FilterField label={t('table.confirmedAt')}>
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
          <DataTable>
            <thead>
              <tr>
                <SortableTh column="id" sort={hSort} onSort={hOnSort}>{t('table.id')}</SortableTh>
                <SortableTh column="bank_transaction_id" sort={hSort} onSort={hOnSort}>{t('table.bankTxnId')}</SortableTh>
                <SortableTh column="status" sort={hSort} onSort={hOnSort}>{t('table.status')}</SortableTh>
                <SortableTh column="confirmed_at" sort={hSort} onSort={hOnSort}>{t('table.confirmedAt')}</SortableTh>
                <th />
              </tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={5} loading={reconciliationsQ.isLoading} empty={reconciliationsQ.data?.items.length === 0} />
              {reconciliationsQ.data?.items.map((r, index) => (
                <tr key={r.payment_reconciliation_id} data-kbd-row={index} className={[r.status === 'reversed' ? 'dim' : '', cursor === index ? 'is-cursor' : ''].filter(Boolean).join(' ') || undefined}>
                  <td className="muted">{r.payment_reconciliation_id}</td>
                  <td className="mono">#{r.bank_transaction_id}</td>
                  <td><StatusBadge map={RECON_STATUS} value={r.status} dot /></td>
                  <td className="muted">{formatDate(r.confirmed_at)}</td>
                  <td className="row-act">
                    {r.status === 'confirmed' && (
                      <Button variant="ghost-danger" size="sm" onClick={() => setReverseTarget(r)}>
                        <Icon name="refresh" size="sm" />{t('reconciliation.reverse')}
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
          <Pager offset={hApplied.offset} pageSize={HPAGE} total={hTotal} onOffsetChange={off => setHApplied(p => ({ ...p, offset: off }))} />
        </Card>
        </>
      )}

      {matchTarget && <ConfirmModal tx={matchTarget} onClose={() => setMatchTarget(null)} />}
      {reverseTarget && <ReverseModal recon={reverseTarget} onClose={() => setReverseTarget(null)} />}
    </>
  )
}
