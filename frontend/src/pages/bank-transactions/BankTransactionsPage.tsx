import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { listBankTransactions, downloadCsv } from '@/api/endpoints'
import type { BankTransaction } from '@/types'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Pager, FilterBar, FilterField, PageHead } from '@/components/ui'
import { yen } from '@/utils/format'

const PAGE = 20

const STATUS_BADGE: Record<BankTransaction['status'], { v: 'warn' | 'info' | 'ok' | 'neut'; label: string }> = {
  unmatched:         { v: 'warn', label: '未消込' },
  partially_matched: { v: 'info', label: '一部消込' },
  matched:           { v: 'ok',   label: '消込済' },
  voided:            { v: 'neut', label: '無効' },
}

type AppliedFilter = { status: string; dateFrom: string; dateTo: string; amountMin: string; amountMax: string; counterparty: string; offset: number }

export default function BankTransactionsPage() {
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
        title="銀行取引一覧"
        sub="取り込んだ入出金取引を絞り込み・確認します。"
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv('/admin/export/bank-transactions', 'bank-transactions.csv')}>
            <Icon name="export" />CSV出力
          </Button>
        }
      />

      <FilterBar>
        <FilterField label="ステータス">
          <select className="inp" value={status} onChange={e => setStatus(e.target.value)}>
            <option value="">すべて</option>
            <option value="unmatched">未消込</option>
            <option value="partially_matched">一部消込</option>
            <option value="matched">消込済</option>
            <option value="voided">無効</option>
          </select>
        </FilterField>
        <FilterField label="入金日 開始">
          <input className="inp" type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
        </FilterField>
        <FilterField label="入金日 終了">
          <input className="inp" type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} />
        </FilterField>
        <FilterField label="金額（以上）">
          <input className="inp tnum" type="number" placeholder="0" style={{ width: 120 }} value={amountMin} onChange={e => setAmountMin(e.target.value)} />
        </FilterField>
        <FilterField label="金額（以下）">
          <input className="inp tnum" type="number" placeholder="—" style={{ width: 120 }} value={amountMax} onChange={e => setAmountMax(e.target.value)} />
        </FilterField>
        <FilterField label="振込人名">
          <div className="inp-icon">
            <Icon name="search" />
            <input className="inp" placeholder="検索" style={{ paddingLeft: 32 }} value={counterparty} onChange={e => setCounterparty(e.target.value)} />
          </div>
        </FilterField>
        <Button variant="primary" onClick={search}><Icon name="search" />検索</Button>
      </FilterBar>

      <Card>
        <DataTable>
          <thead>
            <tr><th>入金日</th><th>金額</th><th>振込人名</th><th>ステータス</th></tr>
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
                  <td><Badge variant={s.v} dot>{s.label}</Badge></td>
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
