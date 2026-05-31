import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listUsers, createUser, deleteUser } from '@/api/endpoints'
import type { User } from '@/types'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Modal, Notice, PageHead } from '@/components/ui'

type Role = 'admin' | 'member' | 'viewer'

const ROLE_MAP: Record<User['role'], { v: 'info' | 'neut'; label: string }> = {
  superadmin: { v: 'info', label: 'Superadmin' },
  admin:  { v: 'info', label: '管理者' },
  member: { v: 'neut', label: 'オペレーター' },
  viewer: { v: 'neut', label: '閲覧者' },
}

function InviteModal({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient()
  const [email, setEmail] = useState('')
  const [role, setRole] = useState<Role>('member')
  const [error, setError] = useState('')
  const mut = useMutation({
    mutationFn: () => createUser(email, role),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['users'] }); onClose() },
    onError: (e: Error) => setError(e.message),
  })
  return (
    <Modal open onClose={onClose} title="ユーザーを招待" sub="招待メールが送信されます。" size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>キャンセル</Button><Button variant="primary" disabled={!email.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon name="send" />{mut.isPending ? '送信中…' : '招待を送信'}</Button></>}
    >
      <div className="field">
        <label>メールアドレス</label>
        <div className="inp-icon"><Icon name="mail" /><input className="inp" type="email" placeholder="user@example.com" style={{ paddingLeft: 34 }} value={email} onChange={e => setEmail(e.target.value)} /></div>
      </div>
      <div className="field">
        <label>ロール</label>
        <select className="inp" value={role} onChange={e => setRole(e.target.value as Role)}>
          <option value="admin">管理者</option>
          <option value="member">オペレーター</option>
          <option value="viewer">閲覧者</option>
        </select>
      </div>
      <Notice variant="info">
        <><b>オペレーター</b>: 消込・督促の実行が可能。設定とユーザー管理は不可。</>
      </Notice>
      {error && <Notice variant="bad">{error}</Notice>}
    </Modal>
  )
}

function DeleteModal({ user, onClose }: { user: User; onClose: () => void }) {
  const qc = useQueryClient()
  const mut = useMutation({
    mutationFn: () => deleteUser(user.user_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['users'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title="このユーザーを削除しますか？" sub={user.email} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>キャンセル</Button><Button variant="danger" disabled={mut.isPending} onClick={() => mut.mutate()}><Icon name="trash" />{mut.isPending ? '削除中…' : '削除'}</Button></>}
    >
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function UsersPage() {
  const [showInvite, setShowInvite] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<User | null>(null)

  const usersQ = useQuery({ queryKey: ['users'], queryFn: ({ signal }) => listUsers({ limit: 100 }, signal) })

  return (
    <>
      <PageHead
        title="ユーザー管理"
        sub="チームメンバーのロールとアクセス権限を管理します。"
        actions={<Button variant="primary" onClick={() => setShowInvite(true)}><Icon name="plus" />ユーザーを招待</Button>}
      />

      <Card>
        <DataTable>
          <thead>
            <tr><th>メールアドレス</th><th>ロール</th><th>ステータス</th><th>最終ログイン</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={5} loading={usersQ.isLoading} empty={usersQ.data?.items.length === 0} />
            {usersQ.data?.items.map(u => {
              const r = ROLE_MAP[u.role]
              return (
                <tr key={u.user_id}>
                  <td className="strong">{u.email}</td>
                  <td><Badge variant={r.v}>{r.label}</Badge></td>
                  <td>{u.status === 'active' ? <Badge variant="ok" dot>有効</Badge> : <Badge variant="warn" dot>招待中</Badge>}</td>
                  <td className="muted">—</td>
                  <td className="row-act">
                    <Button variant="ghost-danger" size="sm" onClick={() => setDeleteTarget(u)}>
                      <Icon name="trash" size="sm" />削除
                    </Button>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </DataTable>
      </Card>

      {showInvite && <InviteModal onClose={() => setShowInvite(false)} />}
      {deleteTarget && <DeleteModal user={deleteTarget} onClose={() => setDeleteTarget(null)} />}
    </>
  )
}
