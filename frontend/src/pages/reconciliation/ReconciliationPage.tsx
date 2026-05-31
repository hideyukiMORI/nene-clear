import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import {
  listUnmatchedTransactions, listReconciliations,
  confirmMatch, reverseReconciliation, proposeMatch, downloadCsv,
} from '@/api/endpoints'
import type { BankTransaction, Reconciliation, UpstreamInvoice } from '@/types'
import type { AllocationInput } from '@/api/endpoints'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Modal, Notice, Tabs, PageHead } from '@/components/ui'
import { yen, formatDate } from '@/utils/format'

// ─── Confirm match modal ───
function ConfirmModal({ tx, onClose }: { tx: BankTransaction; onClose: () => void }) {
  const qc = useQueryClient()
  const [allocs, setAllocs] = useState<AllocationInput[]>([{ invoice_id: 0, amount_cents: 0 }])
  const [reasonCode, setReasonCode] = useState('')

  const suggestQ = useQuery({
    queryKey: ['propose-match', tx.bank_transaction_id],
    queryFn: () => proposeMatch(tx.bank_transaction_id),
    retry: false,
  })

  const confirmMut = useMutation({
    mutationFn: () => confirmMatch(tx.bank_transaction_id, allocs.filter(a => a.invoice_id > 0 && a.amount_cents > 0), reasonCode || undefined),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['bank-transactions', 'unmatched'] })
      void qc.invalidateQueries({ queryKey: ['reconciliations'] })
      onClose()
    },
  })

  function applySuggestion(inv: UpstreamInvoice) {
    setAllocs([{ invoice_id: inv.invoice_id, amount_cents: inv.outstanding_cents }])
  }

  function updateAlloc(i: number, field: keyof AllocationInput, raw: string) {
    setAllocs(prev => prev.map((a, idx) => idx === i ? { ...a, [field]: field === 'amount_cents' ? Math.round(Number(raw) * 100) : Number(raw) } : a))
  }

  const total = allocs.reduce((s, a) => s + a.amount_cents, 0)

  return (
    <Modal
      open
      onClose={onClose}
      title="消込を確定"
      sub={`${tx.counterparty_text} — ${yen(tx.amount_cents)}（${tx.value_date}）`}
      size="wide"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>キャンセル</Button>
          <Button variant="primary" disabled={confirmMut.isPending} onClick={() => confirmMut.mutate()}>
            <Icon name="check" />{confirmMut.isPending ? '処理中…' : '消込を確定'}
          </Button>
        </>
      }
    >
      {/* Suggestions */}
      <div className="sugg">
        <div className="sugg-h"><Icon name="reconcile" size="sm" />マッチ候補</div>
        {suggestQ.isLoading && <p className="muted" style={{ fontSize: 12, margin: 0 }}>候補を検索中…</p>}
        {suggestQ.data?.invoices.length === 0 && <p className="muted" style={{ fontSize: 12, margin: 0 }}>候補が見つかりませんでした</p>}
        {suggestQ.data?.invoices.map(inv => (
          <div key={inv.invoice_id} className="sugg-row">
            <span className="iv mono">{inv.invoice_number}</span>
            <span className="amt">{yen(inv.outstanding_cents)}</span>
            <span className="due">期限 {inv.due_at}</span>
            <Button variant="primary" size="sm" onClick={() => applySuggestion(inv)}>選択</Button>
          </div>
        ))}
      </div>

      {/* Allocations */}
      <div>
        <div className="lbl" style={{ marginBottom: 8 }}>配賦</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {allocs.map((a, i) => (
            <div key={i} className="alloc-row">
              <div className="field"><label style={{ fontSize: 11 }}>請求書ID</label>
                <input className="inp tnum" type="number" value={a.invoice_id || ''} onChange={e => updateAlloc(i, 'invoice_id', e.target.value)} />
              </div>
              <div className="field"><label style={{ fontSize: 11 }}>配賦額（¥）</label>
                <input className="inp tnum" type="number" value={a.amount_cents > 0 ? a.amount_cents / 100 : ''} onChange={e => updateAlloc(i, 'amount_cents', e.target.value)} />
              </div>
              {allocs.length > 1 && (
                <Button variant="ghost" size="sm" style={{ height: 36 }} onClick={() => setAllocs(p => p.filter((_, j) => j !== i))}>
                  <Icon name="x" size="sm" />
                </Button>
              )}
            </div>
          ))}
          <Button variant="link" onClick={() => setAllocs(p => [...p, { invoice_id: 0, amount_cents: 0 }])}>
            <Icon name="plus" size="sm" />配賦を追加
          </Button>
        </div>
      </div>

      <div className="summary-line">
        <span>配賦合計 / 入金額</span>
        <b className="tnum">{yen(total)} / {yen(tx.amount_cents)}</b>
      </div>

      <div className="field">
        <label>理由コード（任意）</label>
        <input className="inp" placeholder="例: 相殺・手数料差引など" value={reasonCode} onChange={e => setReasonCode(e.target.value)} />
      </div>
      {confirmMut.isError && <Notice variant="bad">{confirmMut.error.message}</Notice>}
    </Modal>
  )
}

// ─── Reverse modal ───
function ReverseModal({ recon, onClose }: { recon: Reconciliation; onClose: () => void }) {
  const qc = useQueryClient()
  const [reason, setReason] = useState('')
  const mut = useMutation({
    mutationFn: () => reverseReconciliation(recon.payment_reconciliation_id, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['reconciliations'] }); void qc.invalidateQueries({ queryKey: ['bank-transactions'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title="消込を取消しますか？" size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>キャンセル</Button><Button variant="danger" disabled={!reason.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon name="refresh" size="sm" />{mut.isPending ? '処理中…' : '取消'}</Button></>}
    >
      <div className="field"><label>取消理由</label><input className="inp" value={reason} onChange={e => setReason(e.target.value)} /></div>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function ReconciliationPage() {
  const [tab, setTab] = useState('unmatched')
  const [matchTarget, setMatchTarget] = useState<BankTransaction | null>(null)
  const [reverseTarget, setReverseTarget] = useState<Reconciliation | null>(null)

  const unmatchedQ = useQuery({
    queryKey: ['bank-transactions', 'unmatched', { limit: 50 }],
    queryFn: ({ signal }) => listUnmatchedTransactions({ limit: 50 }, signal),
    enabled: tab === 'unmatched',
  })
  const reconciliationsQ = useQuery({
    queryKey: ['reconciliations'],
    queryFn: ({ signal }) => listReconciliations({ limit: 50 }, signal),
    enabled: tab === 'history',
  })

  return (
    <>
      <PageHead
        title="消込"
        sub="銀行入金と請求を突合し、消込を確定します。"
        actions={
          <Button variant="ghost" onClick={() => void downloadCsv('/admin/export/reconciliations', 'reconciliations.csv')}>
            <Icon name="export" />CSV出力
          </Button>
        }
      />

      <Tabs
        tabs={[
          { key: 'unmatched', label: <><Icon name="reconcile" size="sm" />未消込</> },
          { key: 'history', label: <><Icon name="clock" size="sm" />消込一覧</> },
        ]}
        active={tab}
        onChange={setTab}
      />

      {tab === 'unmatched' && (
        <Card>
          <DataTable>
            <thead>
              <tr><th>入金日</th><th>金額</th><th>振込人名</th><th>突合候補</th><th /></tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={5} loading={unmatchedQ.isLoading} empty={unmatchedQ.data?.items.length === 0} emptyText="未消込の取引はありません" />
              {unmatchedQ.data?.items.map(tx => (
                <tr key={tx.bank_transaction_id}>
                  <td className="muted">{tx.value_date}</td>
                  <td className="num">{yen(tx.amount_cents)}</td>
                  <td className="strong">{tx.counterparty_text}</td>
                  <td><Badge variant="info">候補あり</Badge></td>
                  <td className="row-act">
                    <Button variant="primary" size="sm" onClick={() => setMatchTarget(tx)}>
                      <Icon name="check" size="sm" />消込を確定
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      )}

      {tab === 'history' && (
        <Card>
          <DataTable>
            <thead>
              <tr><th>ID</th><th>銀行取引ID</th><th>ステータス</th><th>確定日</th><th /></tr>
            </thead>
            <tbody>
              <TableStateRow colSpan={5} loading={reconciliationsQ.isLoading} empty={reconciliationsQ.data?.items.length === 0} />
              {reconciliationsQ.data?.items.map(r => (
                <tr key={r.payment_reconciliation_id} className={r.status === 'reversed' ? 'dim' : ''}>
                  <td className="muted">{r.payment_reconciliation_id}</td>
                  <td className="mono">#{r.bank_transaction_id}</td>
                  <td>{r.status === 'confirmed' ? <Badge variant="ok" dot>確定済</Badge> : <Badge variant="neut" dot>取消済</Badge>}</td>
                  <td className="muted">{formatDate(r.confirmed_at)}</td>
                  <td className="row-act">
                    {r.status === 'confirmed' && (
                      <Button variant="ghost-danger" size="sm" onClick={() => setReverseTarget(r)}>
                        <Icon name="refresh" size="sm" />消込を取消
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </DataTable>
        </Card>
      )}

      {matchTarget && <ConfirmModal tx={matchTarget} onClose={() => setMatchTarget(null)} />}
      {reverseTarget && <ReverseModal recon={reverseTarget} onClose={() => setReverseTarget(null)} />}
    </>
  )
}
