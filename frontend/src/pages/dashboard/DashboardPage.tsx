import { useQuery } from '@tanstack/react-query'
import { listUnmatchedTransactions, listDunningNotices } from '@/api/endpoints'
import { Icon, Kpi, KpiGrid, Card, CardHead, DataTable, TableStateRow, Button, PageHead } from '@/components/ui'
import { useNavigate } from 'react-router-dom'
import { yen, formatDate } from '@/utils/format'

export default function DashboardPage() {
  const navigate = useNavigate()

  const unmatchedQ = useQuery({
    queryKey: ['bank-transactions', 'unmatched', { limit: 5 }],
    queryFn: ({ signal }) => listUnmatchedTransactions({ limit: 5 }, signal),
  })

  const dunningQ = useQuery({
    queryKey: ['dunning-notices', { limit: 5 }],
    queryFn: ({ signal }) => listDunningNotices({ limit: 5 }, signal),
  })

  const unmatchedTotal = unmatchedQ.data?.total ?? 0

  return (
    <>
      <PageHead title="ダッシュボード" sub="入金消込・債権状況の概況" />

      <KpiGrid style={{ marginBottom: 20 }}>
        <Kpi
          accent="accent"
          icon={<Icon name="reconcile" />}
          label="未消込の取引"
          value={<>{unmatchedQ.isLoading ? '…' : unmatchedTotal}<small>件</small></>}
          sub={<><Icon name="yen" size="sm" />残高合計取得中</>}
        />
        <Kpi
          accent="bad"
          icon={<Icon name="alert" style={{ color: 'var(--bad)' }} />}
          label="延滞請求（督促対象）"
          value={<>5<small>件</small></>}
          sub={<><Icon name="clock" size="sm" />最長 31 日経過</>}
        />
        <Kpi
          icon={<Icon name="check" />}
          label="今月の消込済"
          value={<span className="tnum">¥4,820,000</span>}
          sub={<><Icon name="trend" size="sm" />前月比 +8.2%</>}
        />
        <Kpi
          accent="warn"
          icon={<Icon name="credit" style={{ color: 'var(--warn)' }} />}
          label="前受金残高"
          value={<span className="tnum">¥45,000</span>}
          sub={<><Icon name="info" size="sm" />適用待ち 2 件</>}
        />
      </KpiGrid>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.5fr) minmax(0,1fr)', gap: 20, alignItems: 'start' }}>
        {/* 未消込テーブル */}
        <Card>
          <CardHead>
            <h2><Icon name="reconcile" />未消込の取引</h2>
            <Button variant="link" onClick={() => navigate('/admin/reconciliation')}>
              すべて表示 <Icon name="chev-r" size="sm" />
            </Button>
          </CardHead>
          <DataTable>
            <colgroup>
              <col style={{ width: 96 }} /><col style={{ width: 96 }} /><col /><col style={{ width: 140 }} />
            </colgroup>
            <thead>
              <tr><th>入金日</th><th>金額</th><th>振込人名</th><th /></tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={4} loading={unmatchedQ.isLoading} empty={unmatchedQ.data?.items.length === 0} emptyText="未消込の取引はありません" />
              {unmatchedQ.data?.items.map(tx => (
                <tr key={tx.bank_transaction_id}>
                  <td className="muted">{tx.value_date}</td>
                  <td className="num">{yen(tx.amount_cents)}</td>
                  <td className="strong">{tx.counterparty_text}</td>
                  <td className="row-act">
                    <Button variant="primary" size="sm" onClick={() => navigate('/admin/reconciliation')}>
                      <Icon name="check" size="sm" />消込を確定
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>

        {/* 最近の督促 */}
        <Card>
          <CardHead>
            <h2><Icon name="bell" />最近の督促</h2>
            <Button variant="link" onClick={() => navigate('/admin/dunning')}>
              督促へ <Icon name="chev-r" size="sm" />
            </Button>
          </CardHead>
          <DataTable>
            <colgroup><col /><col style={{ width: 110 }} /><col style={{ width: 72 }} /></colgroup>
            <thead>
              <tr><th>請求書</th><th>未収残高</th><th>送信日</th></tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={3} loading={dunningQ.isLoading} empty={dunningQ.data?.items.length === 0} />
              {dunningQ.data?.items.map(n => (
                <tr key={n.dunning_notice_id}>
                  <td className="strong mono">{n.invoice_number}</td>
                  <td className="num">{yen(n.outstanding_at_send_cents)}</td>
                  <td className="muted">{formatDate(n.sent_at)}</td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      </div>
    </>
  )
}
