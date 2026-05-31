import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listClientCredits, applyClientCredit } from '@/api/endpoints'
import type { ClientCredit } from '@/types'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Modal, Notice, PageHead } from '@/components/ui'
import { yen, formatDate } from '@/utils/format'

function ApplyModal({ credit, onClose }: { credit: ClientCredit; onClose: () => void }) {
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
      title="前受金を適用"
      sub={`残高 ${yen(credit.remaining_cents)}（元取引 #${credit.source_bank_transaction_id}）`}
      size="narrow"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>キャンセル</Button>
          <Button variant="primary" disabled={!invoiceId || mut.isPending} onClick={() => mut.mutate()}>
            <Icon name="link" />{mut.isPending ? '処理中…' : '適用'}
          </Button>
        </>
      }
    >
      <div className="field"><label>請求書ID</label>
        <input className="inp tnum" type="number" placeholder="例: 123" value={invoiceId} onChange={e => setInvoiceId(e.target.value)} />
      </div>
      <div className="field"><label>適用額（円）</label>
        <input className="inp tnum" type="number" value={amount} onChange={e => setAmount(e.target.value)} />
      </div>
      <div className="summary-line">
        <span>適用後の残高</span>
        <b className="tnum">{yen(Math.max(0, credit.remaining_cents - Math.round(Number(amount) * 100)))}</b>
      </div>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

const STATUS_MAP: Record<ClientCredit['status'], { v: 'ok' | 'warn' | 'neut'; label: string }> = {
  open:              { v: 'ok',   label: '有効' },
  partially_applied: { v: 'warn', label: '一部適用' },
  applied:           { v: 'neut', label: '適用済' },
}

export default function ClientCreditsPage() {
  const [applyTarget, setApplyTarget] = useState<ClientCredit | null>(null)

  const creditsQ = useQuery({
    queryKey: ['client-credits'],
    queryFn: ({ signal }) => listClientCredits({ limit: 100 }, signal),
  })

  return (
    <>
      <PageHead title="前受金" sub="請求に紐づかない入金を前受金として管理し、後から請求へ適用します。" />

      <Card>
        <DataTable>
          <thead>
            <tr><th>ID</th><th>クライアント</th><th>金額</th><th>残高</th><th>ステータス</th><th>元取引</th><th>登録日</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={8} loading={creditsQ.isLoading} empty={creditsQ.data?.items.length === 0} />
            {creditsQ.data?.items.map(c => {
              const s = STATUS_MAP[c.status]
              return (
                <tr key={c.client_credit_id} className={c.status === 'applied' ? 'dim' : ''}>
                  <td className="muted">{c.client_credit_id}</td>
                  <td className="strong">#{c.client_id}</td>
                  <td className="num">{yen(c.amount_cents)}</td>
                  <td className="num">{yen(c.remaining_cents)}</td>
                  <td><Badge variant={s.v} dot>{s.label}</Badge></td>
                  <td className="mono muted">#{c.source_bank_transaction_id}</td>
                  <td className="muted">{formatDate(c.created_at)}</td>
                  <td className="row-act">
                    {c.status !== 'applied' && (
                      <Button variant="primary" size="sm" onClick={() => setApplyTarget(c)}>
                        <Icon name="link" size="sm" />適用
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
