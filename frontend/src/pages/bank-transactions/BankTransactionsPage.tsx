import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { listBankTransactions, bankTransactionsExportPath, downloadCsv } from '@/api/endpoints'
import type { BankTransaction } from '@/types'
import { Button } from '@/shared/ui/button'
import { Card } from '@/shared/ui/card'
import { DataTable, TableStateRow, Pager, SortableTh, nextSort } from '@/shared/ui/data-table'
import type { SortState } from '@/shared/ui/data-table'
import { FilterBar, FilterField } from '@/shared/ui/filter-bar'
import { Icon } from '@/shared/ui/icon'
import { PageHead } from '@/shared/ui/page-head'
import { StatusBadge } from '@/shared/ui/status-badge'
import type { StatusMeta } from '@/shared/ui/status-badge'
import { useTranslation } from '@/shared/i18n/use-translation'
import { useFiscalYearDefault } from '@/hooks/useFiscalYearDefault'
import { useRowCursor } from '@/components/keyboard'
import { yen } from '@/shared/lib/format'

const PAGE = 20

const STATUS_BADGE: Record<BankTransaction['status'], StatusMeta> = {
  unmatched:         { v: 'warn', labelKey: 'bankTransaction.status.unmatched' },
  partially_matched: { v: 'info', labelKey: 'bankTransaction.status.partially_matched' },
  matched:           { v: 'ok',   labelKey: 'bankTransaction.status.matched' },
  voided:            { v: 'neut', labelKey: 'bankTransaction.status.voided' },
}

type AppliedFilter = { status: string; dateFrom: string; dateTo: string; amountMin: string; amountMax: string; counterparty: string; offset: number }

export default function BankTransactionsPage() {
  const { t } = useTranslation()
  // The org's current fiscal year (decided by 決算月) seeds the value-date
  // range. /me is loaded by AppShell before this page mounts, so it's available
  // synchronously here; the Clear button resets to all.
  const { range: fyRange } = useFiscalYearDefault()
  const [status, setStatus] = useState('')
  const [dateFrom, setDateFrom] = useState(fyRange?.fromIso ?? '')
  const [dateTo, setDateTo] = useState(fyRange?.toIso ?? '')
  const [amountMin, setAmountMin] = useState('')
  const [amountMax, setAmountMax] = useState('')
  const [counterparty, setCounterparty] = useState('')
  const [applied, setApplied] = useState<AppliedFilter>({ status: '', dateFrom: fyRange?.fromIso ?? '', dateTo: fyRange?.toIso ?? '', amountMin: '', amountMax: '', counterparty: '', offset: 0 })
  const [sort, setSort] = useState<SortState>({ by: 'value_date', dir: 'desc' })

  // Filter the export mirrors what the list shows (same params, minus paging).
  const txFilter = {
    status: applied.status || undefined,
    value_date_from: applied.dateFrom || undefined,
    value_date_to: applied.dateTo || undefined,
    amount_min_cents: applied.amountMin ? Math.round(Number(applied.amountMin) * 100) : undefined,
    amount_max_cents: applied.amountMax ? Math.round(Number(applied.amountMax) * 100) : undefined,
    counterparty: applied.counterparty || undefined,
    sortBy: sort.by, sortDir: sort.dir,
  }

  const txQ = useQuery({
    queryKey: ['bank-transactions', applied, sort],
    queryFn: ({ signal }) => listBankTransactions({ ...txFilter, limit: PAGE, offset: applied.offset }, signal),
  })

  function search() { setApplied({ status, dateFrom, dateTo, amountMin, amountMax, counterparty, offset: 0 }) }
  function goPage(off: number) { setApplied(p => ({ ...p, offset: off })) }
  function onSort(col: string) { setSort(s => nextSort(s, col)); setApplied(p => ({ ...p, offset: 0 })) }

  const total = txQ.data?.total ?? 0

  // Read-only ledger: j/k move the row cursor; there is no per-row "open" action.
  const rows = txQ.data?.items ?? []
  const cursor = useRowCursor(rows.length, () => undefined)

  return (
    <>
      <PageHead
        title={t('bankTransaction.title')}
        sub={t('bankTransaction.subtitle')}
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv(bankTransactionsExportPath(txFilter), 'bank-transactions.csv')}>
            <Icon name="export" />{t('export.csv')}
          </Button>
        }
      />

      <FilterBar>
        <FilterField label={t('table.status')}>
          <select className="inp" value={status} onChange={e => setStatus(e.target.value)}>
            <option value="">{t('filter.all')}</option>
            <option value="unmatched">{t('bankTransaction.status.unmatched')}</option>
            <option value="partially_matched">{t('bankTransaction.status.partially_matched')}</option>
            <option value="matched">{t('bankTransaction.status.matched')}</option>
            <option value="voided">{t('bankTransaction.status.voided')}</option>
          </select>
        </FilterField>
        <FilterField label={t('bankTransaction.dateFrom')}>
          <input className="inp" type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
        </FilterField>
        <FilterField label={t('bankTransaction.dateTo')}>
          <input className="inp" type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} />
        </FilterField>
        <FilterField label={t('bankTransaction.amountFrom')}>
          <input className="inp tnum" type="number" placeholder="0" style={{ width: 120 }} value={amountMin} onChange={e => setAmountMin(e.target.value)} />
        </FilterField>
        <FilterField label={t('bankTransaction.amountTo')}>
          <input className="inp tnum" type="number" placeholder="—" style={{ width: 120 }} value={amountMax} onChange={e => setAmountMax(e.target.value)} />
        </FilterField>
        <FilterField label={t('table.counterparty')}>
          <div className="inp-icon">
            <Icon name="search" />
            <input className="inp" data-kbd="search" placeholder={t('common.search')} style={{ paddingLeft: 32 }} value={counterparty} onChange={e => setCounterparty(e.target.value)} />
          </div>
        </FilterField>
        <Button variant="primary" onClick={search}><Icon name="search" />{t('common.search')}</Button>
      </FilterBar>

      <Card>
        <DataTable>
          <thead>
            <tr>
              <SortableTh column="value_date" sort={sort} onSort={onSort}>{t('table.valueDate')}</SortableTh>
              <SortableTh column="amount_cents" sort={sort} onSort={onSort}>{t('table.amount')}</SortableTh>
              <SortableTh column="counterparty_text" sort={sort} onSort={onSort}>{t('table.counterparty')}</SortableTh>
              <SortableTh column="status" sort={sort} onSort={onSort}>{t('table.status')}</SortableTh>
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={4} loading={txQ.isLoading} empty={txQ.data?.items.length === 0} />
            {txQ.data?.items.map((tx, index) => (
              <tr key={tx.bank_transaction_id} data-kbd-row={index} className={[tx.status === 'voided' ? 'dim' : '', cursor === index ? 'is-cursor' : ''].filter(Boolean).join(' ') || undefined}>
                <td className="muted">{tx.value_date}</td>
                <td className="num">{yen(tx.amount_cents)}</td>
                <td className="strong">{tx.counterparty_text}</td>
                <td><StatusBadge map={STATUS_BADGE} value={tx.status} dot /></td>
              </tr>
            ))}
          </tbody>
        </DataTable>
        <Pager offset={applied.offset} pageSize={PAGE} total={total} onOffsetChange={goPage} />
      </Card>
    </>
  )
}
