import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  listDunningNotices, sendDunningNotice, listUpstreamInvoices,
  listDunningPauses, pauseDunningNotice, resumeDunningNotice,
} from '@/api/endpoints'
import type { UpstreamInvoice } from '@/types'
import { Icon, Badge, Button, Card, CardHead, DataTable, TableStateRow, Modal, Notice, PageHead } from '@/components/ui'
import { yen, formatDateTime, daysOverdue } from '@/utils/format'

// ─── Send confirm modal ───
function SendModal({ invoice, onClose }: { invoice: UpstreamInvoice; onClose: () => void }) {
  const qc = useQueryClient()
  const mut = useMutation({
    mutationFn: () => sendDunningNotice(invoice.invoice_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-notices'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title="督促メールを送信しますか？" size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>キャンセル</Button><Button variant="primary" disabled={mut.isPending} onClick={() => mut.mutate()}><Icon name="send" />{mut.isPending ? '送信中…' : '督促を送信'}</Button></>}
    >
      <div className="kv">
        <div className="kv-row"><span className="k">請求書</span><span className="v mono">{invoice.invoice_number}</span></div>
        <div className="kv-row"><span className="k">未収残高</span><span className="v">{yen(invoice.outstanding_cents)}</span></div>
        <div className="kv-row"><span className="k">期限</span><span className="v" style={{ color: 'var(--bad)' }}>{invoice.due_at}（{daysOverdue(invoice.due_at)}経過）</span></div>
      </div>
      <Notice variant="info">登録済みのテンプレートで督促メールが送信され、履歴に記録されます。</Notice>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

// ─── Pause modal ───
function PauseModal({ invoiceId, onClose }: { invoiceId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const [reason, setReason] = useState('')
  const mut = useMutation({
    mutationFn: () => pauseDunningNotice(invoiceId, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-pauses'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title="督促を一時停止しますか？" sub={`請求書 #${invoiceId} への督促を止めます。`} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>キャンセル</Button><Button variant="warn" disabled={!reason.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon name="pause" />{mut.isPending ? '処理中…' : '督促を停止'}</Button></>}
    >
      <div className="field"><label>停止理由</label><input className="inp" placeholder="例: 顧客と入金日を調整中" value={reason} onChange={e => setReason(e.target.value)} /></div>
      <Notice variant="warn">停止中は督促が送信されません。理由は履歴に残ります。</Notice>
    </Modal>
  )
}

export default function DunningPage() {
  const qc = useQueryClient()
  const [sendTarget, setSendTarget] = useState<UpstreamInvoice | null>(null)
  const [pauseTarget, setPauseTarget] = useState<number | null>(null)

  const invoicesQ = useQuery({
    queryKey: ['upstream-invoices', { status: 'issued,partially_paid,overdue' }],
    queryFn: ({ signal }) => listUpstreamInvoices({ status: 'issued,partially_paid,overdue' }, signal),
  })
  const noticesQ = useQuery({ queryKey: ['dunning-notices'], queryFn: ({ signal }) => listDunningNotices({ limit: 50 }, signal) })
  const pausesQ = useQuery({ queryKey: ['dunning-pauses', { active_only: true }], queryFn: ({ signal }) => listDunningPauses({ active_only: true, limit: 100 }, signal) })

  const resumeMut = useMutation({
    mutationFn: (id: number) => resumeDunningNotice(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['dunning-pauses'] }); void qc.invalidateQueries({ queryKey: ['upstream-invoices'] }) },
  })

  const activePauses = new Set((pausesQ.data?.items ?? []).map(p => p.invoice_id))

  return (
    <>
      <PageHead title="督促" sub="延滞・未収の請求に対して督促メールを送信し、履歴を管理します。" />

      {/* Pause banner */}
      {activePauses.size > 0 && (
        <div className="card" style={{ borderColor: 'var(--warn-line)', background: 'var(--warn-bg)', marginBottom: 20 }}>
          <div style={{ padding: '14px 18px' }}>
            <div className="row" style={{ color: 'var(--warn)', fontWeight: 700, fontSize: 13, marginBottom: 10 }}>
              <Icon name="pause" /> 督促停止中（{activePauses.size}件）
            </div>
            <div className="wrapw">
              {(pausesQ.data?.items ?? []).map(p => (
                <span key={p.dunning_pause_id} className="tag-pill">
                  <b className="mono">請求書 #{p.invoice_id}</b>
                  <span className="faint">{p.paused_reason}</span>
                  <Button variant="link" onClick={() => resumeMut.mutate(p.invoice_id)}>
                    <Icon name="refresh" size="sm" />督促再開
                  </Button>
                </span>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Eligible invoices */}
      <Card>
        <CardHead>
          <h2><Icon name="alert" />督促対象の請求書</h2>
          <p>延滞・一部入金の請求から督促を送信します。</p>
        </CardHead>
        <DataTable>
          <thead>
            <tr><th>請求書番号</th><th>ステータス</th><th>未収残高</th><th>期限</th><th>経過</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={6} loading={invoicesQ.isLoading} empty={invoicesQ.data?.items.length === 0} emptyText="督促対象の請求書はありません" />
            {invoicesQ.data?.items.map(inv => {
              const paused = activePauses.has(inv.invoice_id)
              const elap = daysOverdue(inv.due_at)
              const daysNum = parseInt(elap)
              return (
                <tr key={inv.invoice_id} className={paused ? 'dim' : ''}>
                  <td className="strong mono">{inv.invoice_number}</td>
                  <td>
                    {inv.status === 'overdue' ? <Badge variant="bad" dot>延滞</Badge> : <Badge variant="warn" dot>一部入金</Badge>}
                  </td>
                  <td className="num">{yen(inv.outstanding_cents)}</td>
                  <td className="muted">{inv.due_at}</td>
                  <td style={{ color: daysNum > 0 ? (daysNum > 14 ? 'var(--bad)' : 'var(--warn)') : 'var(--muted)', fontWeight: 600 }}>{elap}</td>
                  <td className="row-act">
                    {paused
                      ? <Badge variant="warn"><Icon name="pause" size="sm" />停止中</Badge>
                      : <Button variant="primary" size="sm" onClick={() => setSendTarget(inv)}><Icon name="send" size="sm" />督促を送信</Button>
                    }
                    {!paused && (
                      <Button variant="ghost-warn" size="sm" onClick={() => setPauseTarget(inv.invoice_id)}>
                        <Icon name="pause" size="sm" />停止
                      </Button>
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </DataTable>
      </Card>

      {/* History */}
      <Card>
        <CardHead><h2><Icon name="clock" />督促履歴</h2></CardHead>
        <DataTable>
          <thead>
            <tr><th>請求書番号</th><th>送信先</th><th>未収残高</th><th>送信日時</th><th>送信者</th></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={5} loading={noticesQ.isLoading} empty={noticesQ.data?.items.length === 0} />
            {noticesQ.data?.items.map(n => (
              <tr key={n.dunning_notice_id}>
                <td className="strong mono">{n.invoice_number}</td>
                <td className="muted">{n.recipient_email}</td>
                <td className="num">{yen(n.outstanding_at_send_cents)}</td>
                <td className="muted">{formatDateTime(n.sent_at)}</td>
                <td className="muted">#{n.sent_by}</td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      {sendTarget && <SendModal invoice={sendTarget} onClose={() => setSendTarget(null)} />}
      {pauseTarget !== null && <PauseModal invoiceId={pauseTarget} onClose={() => setPauseTarget(null)} />}
    </>
  )
}
