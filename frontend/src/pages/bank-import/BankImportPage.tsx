import { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listBankImportBatches, importBankCsv, reverseBankImportBatch, getClearSettings } from '@/api/endpoints'
import type { BankImportBatch } from '@/types'
import { Icon, Badge, Button, Card, CardHead, CardBody, DataTable, TableStateRow, Notice, Modal, PageHead } from '@/components/ui'
import { formatDate } from '@/utils/format'

interface ReverseModalProps { batch: BankImportBatch; onClose: () => void }
function ReverseModal({ batch, onClose }: ReverseModalProps) {
  const qc = useQueryClient()
  const [reason, setReason] = useState('')
  const mut = useMutation({
    mutationFn: () => reverseBankImportBatch(batch.bank_import_batch_id, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['bank-import-batches'] }); onClose() },
  })
  return (
    <Modal
      open
      onClose={onClose}
      title="このバッチを取消しますか？"
      sub={batch.source_filename}
      size="narrow"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>キャンセル</Button>
          <Button variant="danger" disabled={!reason.trim() || mut.isPending} onClick={() => mut.mutate()}>
            <Icon name="trash" size="sm" />{mut.isPending ? '処理中…' : '取消'}
          </Button>
        </>
      }
    >
      <div className="field">
        <label>取消理由</label>
        <input className="inp" value={reason} onChange={e => setReason(e.target.value)} />
      </div>
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function BankImportPage() {
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const [accountId, setAccountId] = useState<number | ''>('')
  const [reverseTarget, setReverseTarget] = useState<BankImportBatch | null>(null)
  const [uploadMsg, setUploadMsg] = useState<{ ok: boolean; text: string } | null>(null)

  const settingsQ = useQuery({ queryKey: ['clear-settings'], queryFn: ({ signal }) => getClearSettings(signal) })
  const batchQ = useQuery({ queryKey: ['bank-import-batches'], queryFn: ({ signal }) => listBankImportBatches({ limit: 50 }, signal) })

  const uploadMut = useMutation({
    mutationFn: ({ id, file }: { id: number; file: File }) => importBankCsv(id, file),
    onSuccess: (data: unknown) => {
      const rows = (data as { row_count?: number })?.row_count ?? '?'
      setUploadMsg({ ok: true, text: `取込完了 — ${rows} 件の取引を登録しました。` })
      setAccountId('')
      if (fileRef.current) fileRef.current.value = ''
      void qc.invalidateQueries({ queryKey: ['bank-import-batches'] })
      void qc.invalidateQueries({ queryKey: ['bank-transactions'] })
    },
    onError: (err: Error) => setUploadMsg({ ok: false, text: err.message }),
  })

  function handleUpload(e: React.FormEvent) {
    e.preventDefault()
    setUploadMsg(null)
    const file = fileRef.current?.files?.[0]
    if (!file || accountId === '') { setUploadMsg({ ok: false, text: '銀行口座とファイルを選択してください' }); return }
    uploadMut.mutate({ id: Number(accountId), file })
  }

  const accounts = settingsQ.data?.bank_accounts ?? []

  return (
    <>
      <PageHead title="銀行CSV取込" sub="銀行の入出金明細CSVを取り込み、消込対象の取引を登録します。" />

      <Card style={{ maxWidth: 760 }}>
        <CardHead><h2><Icon name="cloud-up" />CSVをアップロード</h2></CardHead>
        <CardBody className="stack">
          <form onSubmit={handleUpload} className="stack">
            <div className="field" style={{ maxWidth: 380 }}>
              <label>銀行口座を選択</label>
              <select className="inp" value={accountId} onChange={e => setAccountId(e.target.value === '' ? '' : Number(e.target.value))}>
                <option value="">銀行口座を選択</option>
                {accounts.map(a => (
                  <option key={a.bank_account_id} value={a.bank_account_id}>
                    {a.bank_name} {a.bank_branch} {a.account_type === 'ordinary' ? '普通' : '当座'} {a.account_number}
                  </option>
                ))}
              </select>
            </div>
            <div className="field">
              <label>ファイル</label>
              <div className="dropzone">
                <span className="dz-ic"><Icon name="file" /></span>
                <div style={{ flex: 1 }}>
                  <input ref={fileRef} type="file" accept=".csv" style={{ fontSize: 13 }} />
                  <small style={{ display: 'block', marginTop: 4 }}>CSVをドラッグ＆ドロップ、またはクリックして選択</small>
                </div>
              </div>
            </div>
            {uploadMsg && <Notice variant={uploadMsg.ok ? 'ok' : 'bad'}>{uploadMsg.text}</Notice>}
            <div className="row">
              <Button variant="primary" type="submit" disabled={uploadMut.isPending}>
                <Icon name="import" />{uploadMut.isPending ? '取込中…' : '取込む'}
              </Button>
              <span className="faint" style={{ fontSize: 12 }}>重複行は自動でスキップされます。</span>
            </div>
          </form>
        </CardBody>
      </Card>

      <Card>
        <CardHead><h2><Icon name="clock" />取込履歴</h2></CardHead>
        <DataTable>
          <thead>
            <tr><th>ID</th><th>ファイル名</th><th>件数</th><th>ステータス</th><th>取込日</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={6} loading={batchQ.isLoading} empty={batchQ.data?.items.length === 0} />
            {batchQ.data?.items.map(b => (
              <tr key={b.bank_import_batch_id} className={b.status === 'reversed' ? 'dim' : ''}>
                <td className="muted">{b.bank_import_batch_id}</td>
                <td className="strong mono">{b.source_filename}</td>
                <td className="num">{b.row_count}</td>
                <td>{b.status === 'imported'
                  ? <Badge variant="ok" dot>取込済</Badge>
                  : <Badge variant="neut" dot>取消済</Badge>}
                </td>
                <td className="muted">{formatDate(b.imported_at)}</td>
                <td className="row-act">
                  {b.status === 'imported' && (
                    <Button variant="ghost-danger" size="sm" onClick={() => setReverseTarget(b)}>
                      <Icon name="trash" size="sm" />取消
                    </Button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      {reverseTarget && <ReverseModal batch={reverseTarget} onClose={() => setReverseTarget(null)} />}
    </>
  )
}
