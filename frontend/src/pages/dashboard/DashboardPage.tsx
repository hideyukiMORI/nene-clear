import { useQuery } from '@tanstack/react-query'
import { listUnmatchedTransactions, listDunningNotices } from '@/shared/api/endpoints'
import { Button } from '@hideyukimori/nene2-ui'
import { Card } from '@hideyukimori/nene2-ui'
import { CardHead } from '@/shared/ui/card'
import { DataTable, TableStateRow } from '@/shared/ui/data-table'
import { Icon } from '@/shared/ui/icon'
import { Kpi, KpiGrid } from '@/shared/ui/kpi'
import { PageHead } from '@/shared/ui/page-head'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from '@/shared/i18n/use-translation'
import { yen, formatDate } from '@/shared/lib/format'

export default function DashboardPage() {
  const { t } = useTranslation()
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
  const unit = t('common.unitItems')

  return (
    <>
      <PageHead title={t('dashboard.title')} sub={t('dashboard.subtitle')} />

      <KpiGrid style={{ marginBottom: 20 }}>
        <Kpi
          accent="accent"
          testId="kpi-unmatched"
          icon={<Icon decorative name="reconcile" />}
          label={t('dashboard.kpi.unmatched')}
          value={<>{unmatchedQ.isLoading ? '…' : unmatchedTotal}{unit && <small>{unit}</small>}</>}
          sub={<><Icon decorative name="yen" size="sm" />{t('dashboard.kpi.unmatchedSub')}</>}
        />
        <Kpi
          accent="bad"
          icon={<Icon decorative name="alert" style={{ color: 'var(--bad)' }} />}
          label={t('dashboard.kpi.overdue')}
          value={<>5{unit && <small>{unit}</small>}</>}
          sub={<><Icon decorative name="clock" size="sm" />{t('dashboard.kpi.overdueSub')}</>}
        />
        <Kpi
          icon={<Icon decorative name="check" />}
          label={t('dashboard.kpi.cleared')}
          value={<span className="tnum">¥4,820,000</span>}
          sub={<><Icon decorative name="trend" size="sm" />{t('dashboard.kpi.clearedSub')}</>}
        />
        <Kpi
          accent="warn"
          icon={<Icon decorative name="credit" style={{ color: 'var(--warn)' }} />}
          label={t('dashboard.kpi.credit')}
          value={<span className="tnum">¥45,000</span>}
          sub={<><Icon decorative name="info" size="sm" />{t('dashboard.kpi.creditSub')}</>}
        />
      </KpiGrid>

      <div className="dash-cards" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.5fr) minmax(0,1fr)', gap: 20, alignItems: 'start' }}>
        {/* Unmatched transactions */}
        <Card pad="none" className="min-w-0 mt-x-md">
          <CardHead>
            <h2><Icon decorative name="reconcile" />{t('dashboard.unmatched')}</h2>
            <Button variant="link" onClick={() => navigate('/admin/reconciliation')}>
              {t('dashboard.viewAll')} <Icon decorative name="chev-r" size="sm" />
            </Button>
          </CardHead>
          <DataTable>
            <colgroup>
              <col style={{ width: 96 }} /><col style={{ width: 96 }} /><col /><col style={{ width: 140 }} />
            </colgroup>
            <thead>
              <tr><th>{t('table.valueDate')}</th><th>{t('table.amount')}</th><th>{t('table.counterparty')}</th><th /></tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={4} loading={unmatchedQ.isLoading} empty={unmatchedQ.data?.items.length === 0} emptyKey="dashboard.noUnmatched" />
              {unmatchedQ.data?.items.map(tx => (
                <tr key={tx.bank_transaction_id}>
                  <td className="muted">{tx.value_date}</td>
                  <td className="num">{yen(tx.amount_cents)}</td>
                  <td className="strong">{tx.counterparty_text}</td>
                  <td className="row-act">
                    <Button size="sm" onClick={() => navigate('/admin/reconciliation')}>
                      <Icon decorative name="check" size="sm" />{t('reconciliation.confirm')}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>

        {/* Recent dunning */}
        <Card pad="none" className="min-w-0 mt-x-md">
          <CardHead>
            <h2><Icon decorative name="bell" />{t('dashboard.recentDunning')}</h2>
            <Button variant="link" onClick={() => navigate('/admin/dunning')}>
              {t('dashboard.toDunning')} <Icon decorative name="chev-r" size="sm" />
            </Button>
          </CardHead>
          <DataTable>
            <colgroup><col /><col style={{ width: 110 }} /><col style={{ width: 72 }} /></colgroup>
            <thead>
              <tr><th>{t('table.invoice')}</th><th>{t('table.outstanding')}</th><th>{t('table.sentDate')}</th></tr>
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
