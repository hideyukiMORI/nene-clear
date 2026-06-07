import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listClientCredits, applyClientCredit, downloadCsv, clientCreditsExportPath } from '@/api/endpoints'
import type { ClientCredit } from '@/types'
import { Icon, StatusBadge, Button, Card, DataTable, TableStateRow, Modal, Notice, PageHead, FilterBar, FilterField, DatePicker, SortableTh, nextSort, Pager } from '@/components/ui'
import type { StatusMeta, SortState } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import { useRowCursor } from '@/components/keyboard'
import { yen, formatDate } from '@/utils/format'

function ApplyModal({ credit, onClose }: { credit: ClientCredit; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [invoiceId, setInvoiceId] = useState('')
  const [amount, setAmount] = useState(String(credit.remaining_cents / 100))
  const mut = useMutation({
    mutationFn: () => applyClientCredit(credit.client_credit_id, Number(invoiceId), Math.round(Number(amount) * 100)),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['client-credits'] }); onClose() },
  })
  return (
    <Modal
      open
      onClose={onClose}
      title={t('clientCredit.applyModal.title')}
      sub={t('clientCredit.applyModal.balance', { amount: yen(credit.remaining_cents), txn: credit.source_bank_transaction_id })}
      size="narrow"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="primary" disabled={!invoiceId || mut.isPending} onClick={() => mut.mutate()}>
            <Icon name="link" />{mut.isPending ? t('common.processing') : t('clientCredit.apply')}
          </Button>
        </>
      }
    >
      <div className="field"><label>{t('clientCredit.applyModal.invoiceId')}</label>
        <input className="inp tnum" type="number" placeholder={t('clientCredit.applyModal.invoiceIdPlaceholder')} value={invoiceId} onChange={e => setInvoiceId(e.target.value)} />
      </div>
      <div className="field"><label>{t('clientCredit.applyModal.amount')}</label>
        <input className="inp tnum" type="number" value={amount} onChange={e => setAmount(e.target.value)} />
      </div>
      <div className="summary-line">
        <span>{t('clientCredit.applyModal.afterBalance')}</span>
        <b className="tnum">{yen(Math.max(0, credit.remaining_cents - Math.round(Number(amount) * 100)))}</b>
      </div>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

const STATUS_MAP: Record<ClientCredit['status'], StatusMeta> = {
  open:   { v: 'ok',   labelKey: 'clientCredit.status.open' },
  voided: { v: 'neut', labelKey: 'clientCredit.status.voided' },
}

const PAGE = 50
type Filters = { clientId: string; status: string; amountMin: string; amountMax: string; remainingMin: string; remainingMax: string; createdFrom: string; createdTo: string }
const EMPTY: Filters = { clientId: '', status: '', amountMin: '', amountMax: '', remainingMin: '', remainingMax: '', createdFrom: '', createdTo: '' }
const yenToCents = (v: string) => (v ? Math.round(Number(v) * 100) : undefined)
const isoDate = (v: string) => (v ? v.replace(/\//g, '-') : undefined)

export default function ClientCreditsPage() {
  const { t } = useTranslation()
  const [applyTarget, setApplyTarget] = useState<ClientCredit | null>(null)

  const [f, setF] = useState<Filters>(EMPTY)
  const [applied, setApplied] = useState<Filters>(EMPTY)
  const [sort, setSort] = useState<SortState>({ by: 'id', dir: 'desc' })
  const [offset, setOffset] = useState(0)
  const set = (k: keyof Filters) => (v: string) => setF(prev => ({ ...prev, [k]: v }))

  // Export mirrors the list's current filter (same params, minus paging).
  const creditFilter = {
    clientId: applied.clientId || undefined,
    status: applied.status || undefined,
    amountMinCents: yenToCents(applied.amountMin),
    amountMaxCents: yenToCents(applied.amountMax),
    remainingMinCents: yenToCents(applied.remainingMin),
    remainingMaxCents: yenToCents(applied.remainingMax),
    createdFrom: isoDate(applied.createdFrom),
    createdTo: isoDate(applied.createdTo),
    sortBy: sort.by,
    sortDir: sort.dir,
  }
  const creditsQ = useQuery({
    queryKey: ['client-credits', applied, sort, offset],
    queryFn: ({ signal }) => listClientCredits({ ...creditFilter, limit: PAGE, offset }, signal),
  })

  function search() { setApplied(f); setOffset(0) }
  function clear() { setF(EMPTY); setApplied(EMPTY); setOffset(0) }
  function onSort(col: string) { setSort(s => nextSort(s, col)); setOffset(0) }

  const total = creditsQ.data?.total ?? 0

  // Row cursor (j/k); Enter/o opens the apply dialog for the cursored credit.
  const rows = creditsQ.data?.items ?? []
  const cursor = useRowCursor(rows.length, (i) => {
    const c = rows[i]
    if (c && c.status === 'open') setApplyTarget(c)
  })

  return (
    <>
      <PageHead
        title={t('clientCredit.title')}
        sub={t('clientCredit.subtitle')}
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv(clientCreditsExportPath(creditFilter), 'client-credits.csv')}>
            <Icon name="export" />{t('export.csv')}
          </Button>
        }
      />

      <FilterBar>
        <FilterField label={t('table.client')}>
          <div className="inp-icon">
            <Icon name="search" />
            <input className="inp" data-kbd="search" style={{ paddingLeft: 32, width: 150 }} placeholder={t('common.search')} value={f.clientId} onChange={e => set('clientId')(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.status')}>
          <select className="inp" value={f.status} onChange={e => set('status')(e.target.value)}>
            <option value="">{t('filter.all')}</option>
            <option value="open">{t('clientCredit.status.open')}</option>
            <option value="voided">{t('clientCredit.status.voided')}</option>
          </select>
        </FilterField>
        <FilterField label={t('table.amount')}>
          <div className="range-pair">
            <input className="inp tnum" type="number" value={f.amountMin} onChange={e => set('amountMin')(e.target.value)} />
            <span className="tilde">〜</span>
            <input className="inp tnum" type="number" value={f.amountMax} onChange={e => set('amountMax')(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.remaining')}>
          <div className="range-pair">
            <input className="inp tnum" type="number" value={f.remainingMin} onChange={e => set('remainingMin')(e.target.value)} />
            <span className="tilde">〜</span>
            <input className="inp tnum" type="number" value={f.remainingMax} onChange={e => set('remainingMax')(e.target.value)} />
          </div>
        </FilterField>
        <FilterField label={t('table.createdAt')}>
          <div className="range-pair">
            <DatePicker value={f.createdFrom} onChange={set('createdFrom')} />
            <span className="tilde">〜</span>
            <DatePicker value={f.createdTo} onChange={set('createdTo')} />
          </div>
        </FilterField>
        <div className="filter-actions">
          <span className="filter-count">{t('filter.count', { n: total })}</span>
          <Button variant="ghost" size="sm" onClick={clear}><Icon name="refresh" size="sm" />{t('filter.clear')}</Button>
          <Button variant="primary" size="sm" onClick={search}><Icon name="search" size="sm" />{t('common.search')}</Button>
        </div>
      </FilterBar>

      <Card>
        <DataTable>
          <thead>
            <tr>
              <SortableTh column="id" sort={sort} onSort={onSort}>{t('table.id')}</SortableTh>
              <SortableTh column="client_id" sort={sort} onSort={onSort}>{t('table.client')}</SortableTh>
              <SortableTh column="amount_cents" sort={sort} onSort={onSort}>{t('table.amount')}</SortableTh>
              <SortableTh column="remaining_cents" sort={sort} onSort={onSort}>{t('table.remaining')}</SortableTh>
              <th>{t('table.status')}</th>
              <th>{t('table.sourceTransaction')}</th>
              <SortableTh column="created_at" sort={sort} onSort={onSort}>{t('table.createdAt')}</SortableTh>
              <th />
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={8} loading={creditsQ.isLoading} empty={creditsQ.data?.items.length === 0} emptyKey="filter.noMatch" />
            {creditsQ.data?.items.map((c, index) => (
              <tr key={c.client_credit_id} data-kbd-row={index} className={[c.status === 'voided' ? 'dim' : '', cursor === index ? 'is-cursor' : ''].filter(Boolean).join(' ') || undefined}>
                <td className="muted">{c.client_credit_id}</td>
                <td className="strong">#{c.client_id}</td>
                <td className="num">{yen(c.amount_cents)}</td>
                <td className="num">{yen(c.remaining_cents)}</td>
                <td><StatusBadge map={STATUS_MAP} value={c.status} dot /></td>
                <td className="mono muted">#{c.source_bank_transaction_id}</td>
                <td className="muted">{formatDate(c.created_at)}</td>
                <td className="row-act">
                  {c.status === 'open' && (
                    <Button variant="primary" size="sm" onClick={() => setApplyTarget(c)}>
                      <Icon name="link" size="sm" />{t('clientCredit.apply')}
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </DataTable>
        <Pager offset={offset} pageSize={PAGE} total={total} onOffsetChange={setOffset} />
      </Card>

      {applyTarget && <ApplyModal credit={applyTarget} onClose={() => setApplyTarget(null)} />}
    </>
  )
}
