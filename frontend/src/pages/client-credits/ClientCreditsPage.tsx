import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listClientCredits, applyClientCredit } from '@/api/endpoints'
import type { ClientCredit } from '@/types'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Modal, Notice, PageHead } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import type { MessageKey } from '@/locales'
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

const STATUS_MAP: Record<ClientCredit['status'], { v: 'ok' | 'neut'; labelKey: MessageKey }> = {
  open:   { v: 'ok',   labelKey: 'clientCredit.status.open' },
  voided: { v: 'neut', labelKey: 'clientCredit.status.voided' },
}

export default function ClientCreditsPage() {
  const { t } = useTranslation()
  const [applyTarget, setApplyTarget] = useState<ClientCredit | null>(null)

  const creditsQ = useQuery({
    queryKey: ['client-credits'],
    queryFn: ({ signal }) => listClientCredits({ limit: 100 }, signal),
  })

  return (
    <>
      <PageHead title={t('clientCredit.title')} sub={t('clientCredit.subtitle')} />

      <Card>
        <DataTable>
          <thead>
            <tr><th>{t('table.id')}</th><th>{t('table.client')}</th><th>{t('table.amount')}</th><th>{t('table.remaining')}</th><th>{t('table.status')}</th><th>{t('table.sourceTransaction')}</th><th>{t('table.createdAt')}</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={8} loading={creditsQ.isLoading} empty={creditsQ.data?.items.length === 0} />
            {creditsQ.data?.items.map(c => {
              const s = STATUS_MAP[c.status]
              return (
                <tr key={c.client_credit_id} className={c.status === 'voided' ? 'dim' : ''}>
                  <td className="muted">{c.client_credit_id}</td>
                  <td className="strong">#{c.client_id}</td>
                  <td className="num">{yen(c.amount_cents)}</td>
                  <td className="num">{yen(c.remaining_cents)}</td>
                  <td><Badge variant={s.v} dot>{t(s.labelKey)}</Badge></td>
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
              )
            })}
          </tbody>
        </DataTable>
      </Card>

      {applyTarget && <ApplyModal credit={applyTarget} onClose={() => setApplyTarget(null)} />}
    </>
  )
}
