import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { listBankTransactions, downloadCsv } from '@/api/endpoints'
import type { BankTransaction } from '@/types'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Pager, FilterBar, FilterField, PageHead } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import type { MessageKey } from '@/locales'
import { yen } from '@/utils/format'

const PAGE = 20

const STATUS_BADGE: Record<BankTransaction['status'], { v: 'warn' | 'info' | 'ok' | 'neut'; labelKey: MessageKey }> = {
  unmatched:         { v: 'warn', labelKey: 'bankTransaction.status.unmatched' },
  partially_matched: { v: 'info', labelKey: 'bankTransaction.status.partially_matched' },
  matched:           { v: 'ok',   labelKey: 'bankTransaction.status.matched' },
  voided:            { v: 'neut', labelKey: 'bankTransaction.status.voided' },
}

type AppliedFilter = { status: string; dateFrom: string; dateTo: string; amountMin: string; amountMax: string; counterparty: string; offset: number }

export default function BankTransactionsPage() {
  const { t } = useTranslation()
  const [status, setStatus] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [amountMin, setAmountMin] = useState('')
  const [amountMax, setAmountMax] = useState('')
  const [counterparty, setCounterparty] = useState('')
  const [applied, setApplied] = useState<AppliedFilter>({ status: '', dateFrom: '', dateTo: '', amountMin: '', amountMax: '', counterparty: '', offset: 0 })

  const txQ = useQuery({
    queryKey: ['bank-transactions', applied],
    queryFn: ({ signal }) => listBankTransactions({
      status: applied.status || undefined,
      value_date_from: applied.dateFrom || undefined,
      value_date_to: applied.dateTo || undefined,
      amount_min_cents: applied.amountMin ? Math.round(Number(applied.amountMin) * 100) : undefined,
      amount_max_cents: applied.amountMax ? Math.round(Number(applied.amountMax) * 100) : undefined,
      counterparty: applied.counterparty || undefined,
      limit: PAGE, offset: applied.offset,
    }, signal),
  })

  function search() { setApplied({ status, dateFrom, dateTo, amountMin, amountMax, counterparty, offset: 0 }) }
  function goPage(off: number) { setApplied(p => ({ ...p, offset: off })) }

  const total = txQ.data?.total ?? 0
  const currentPage = Math.floor(applied.offset / PAGE) + 1
  const totalPages = Math.ceil(total / PAGE)

  return (
    <>
      <PageHead
        title={t('bankTransaction.title')}
        sub={t('bankTransaction.subtitle')}
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv('/admin/export/bank-transactions', 'bank-transactions.csv')}>
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
            <input className="inp" placeholder={t('common.search')} style={{ paddingLeft: 32 }} value={counterparty} onChange={e => setCounterparty(e.target.value)} />
          </div>
        </FilterField>
        <Button variant="primary" onClick={search}><Icon name="search" />{t('common.search')}</Button>
      </FilterBar>

      <Card>
        <DataTable>
          <thead>
            <tr><th>{t('table.valueDate')}</th><th>{t('table.amount')}</th><th>{t('table.counterparty')}</th><th>{t('table.status')}</th></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={4} loading={txQ.isLoading} empty={txQ.data?.items.length === 0} />
            {txQ.data?.items.map(tx => {
              const s = STATUS_BADGE[tx.status]
              return (
                <tr key={tx.bank_transaction_id} className={tx.status === 'voided' ? 'dim' : ''}>
                  <td className="muted">{tx.value_date}</td>
                  <td className="num">{yen(tx.amount_cents)}</td>
                  <td className="strong">{tx.counterparty_text}</td>
                  <td><Badge variant={s.v} dot>{t(s.labelKey)}</Badge></td>
                </tr>
              )
            })}
          </tbody>
        </DataTable>
        {total > PAGE && (
          <Pager
            current={currentPage}
            total={totalPages}
            onPrev={() => goPage(Math.max(0, applied.offset - PAGE))}
            onNext={() => goPage(applied.offset + PAGE)}
            itemCount={total}
            pageSize={PAGE}
            offset={applied.offset}
          />
        )}
      </Card>
    </>
  )
}
