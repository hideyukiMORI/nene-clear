import { useQuery } from '@tanstack/react-query'
import { listUnmatchedTransactions, listDunningNotices } from '@/api/endpoints'
import { Icon, Kpi, KpiGrid, Card, CardHead, DataTable, TableStateRow, Button, PageHead } from '@/components/ui'
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
          icon={<Icon name="reconcile" />}
          label={t('dashboard.kpi.unmatched')}
          value={<>{unmatchedQ.isLoading ? '…' : unmatchedTotal}{unit && <small>{unit}</small>}</>}
          sub={<><Icon name="yen" size="sm" />{t('dashboard.kpi.unmatchedSub')}</>}
        />
        <Kpi
          accent="bad"
          icon={<Icon name="alert" style={{ color: 'var(--bad)' }} />}
          label={t('dashboard.kpi.overdue')}
          value={<>5{unit && <small>{unit}</small>}</>}
          sub={<><Icon name="clock" size="sm" />{t('dashboard.kpi.overdueSub')}</>}
        />
        <Kpi
          icon={<Icon name="check" />}
          label={t('dashboard.kpi.cleared')}
          value={<span className="tnum">¥4,820,000</span>}
          sub={<><Icon name="trend" size="sm" />{t('dashboard.kpi.clearedSub')}</>}
        />
        <Kpi
          accent="warn"
          icon={<Icon name="credit" style={{ color: 'var(--warn)' }} />}
          label={t('dashboard.kpi.credit')}
          value={<span className="tnum">¥45,000</span>}
          sub={<><Icon name="info" size="sm" />{t('dashboard.kpi.creditSub')}</>}
        />
      </KpiGrid>

      <div className="dash-cards" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.5fr) minmax(0,1fr)', gap: 20, alignItems: 'start' }}>
        {/* Unmatched transactions */}
        <Card>
          <CardHead>
            <h2><Icon name="reconcile" />{t('dashboard.unmatched')}</h2>
            <Button variant="link" onClick={() => navigate('/admin/reconciliation')}>
              {t('dashboard.viewAll')} <Icon name="chev-r" size="sm" />
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
                    <Button variant="primary" size="sm" onClick={() => navigate('/admin/reconciliation')}>
                      <Icon name="check" size="sm" />{t('reconciliation.confirm')}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>

        {/* Recent dunning */}
        <Card>
          <CardHead>
            <h2><Icon name="bell" />{t('dashboard.recentDunning')}</h2>
            <Button variant="link" onClick={() => navigate('/admin/dunning')}>
              {t('dashboard.toDunning')} <Icon name="chev-r" size="sm" />
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
