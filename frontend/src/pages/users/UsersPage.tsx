import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listUsers, createUser, deleteUser } from '@/api/endpoints'
import type { User } from '@/types'
import { Icon, Badge, Button, Card, DataTable, TableStateRow, Modal, Notice, PageHead } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import type { MessageKey } from '@/locales'

type Role = 'admin' | 'member' | 'viewer'

const ROLE_MAP: Record<User['role'], { v: 'info' | 'neut'; labelKey: MessageKey }> = {
  superadmin: { v: 'info', labelKey: 'users.role.superadmin' },
  admin:  { v: 'info', labelKey: 'users.role.admin' },
  member: { v: 'neut', labelKey: 'users.role.member' },
  viewer: { v: 'neut', labelKey: 'users.role.viewer' },
}

function InviteModal({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
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
    <Modal open onClose={onClose} title={t('users.invite')} sub={t('users.inviteModal.subtitle')} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="primary" disabled={!email.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon name="send" />{mut.isPending ? t('common.sending') : t('users.inviteSubmit')}</Button></>}
    >
      <div className="field">
        <label>{t('users.email')}</label>
        <div className="inp-icon"><Icon name="mail" /><input className="inp" type="email" placeholder="user@example.com" style={{ paddingLeft: 34 }} value={email} onChange={e => setEmail(e.target.value)} /></div>
      </div>
      <div className="field">
        <label>{t('users.role')}</label>
        <select className="inp" value={role} onChange={e => setRole(e.target.value as Role)}>
          <option value="admin">{t('users.role.admin')}</option>
          <option value="member">{t('users.role.member')}</option>
          <option value="viewer">{t('users.role.viewer')}</option>
        </select>
      </div>
      <Notice variant="info">
        <><b>{t('users.role.member')}</b>{t('users.roleInfoDesc')}</>
      </Notice>
      {error && <Notice variant="bad">{error}</Notice>}
    </Modal>
  )
}

function DeleteModal({ user, onClose }: { user: User; onClose: () => void }) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const mut = useMutation({
    mutationFn: () => deleteUser(user.user_id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['users'] }); onClose() },
  })
  return (
    <Modal open onClose={onClose} title={t('users.confirmDelete')} sub={user.email} size="narrow"
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="danger" disabled={mut.isPending} onClick={() => mut.mutate()}><Icon name="trash" />{mut.isPending ? t('common.processing') : t('users.delete')}</Button></>}
    >
      {mut.isError && <Notice variant="bad">{mut.error.message}</Notice>}
    </Modal>
  )
}

export default function UsersPage() {
  const { t } = useTranslation()
  const [showInvite, setShowInvite] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<User | null>(null)

  const usersQ = useQuery({ queryKey: ['users'], queryFn: ({ signal }) => listUsers({ limit: 100 }, signal) })

  return (
    <>
      <PageHead
        title={t('users.title')}
        sub={t('users.subtitle')}
        actions={<Button variant="primary" onClick={() => setShowInvite(true)}><Icon name="plus" />{t('users.invite')}</Button>}
      />

      <Card>
        <DataTable>
          <thead>
            <tr><th>{t('table.email')}</th><th>{t('table.role')}</th><th>{t('table.status')}</th><th>{t('table.lastLogin')}</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={5} loading={usersQ.isLoading} empty={usersQ.data?.items.length === 0} />
            {usersQ.data?.items.map(u => {
              const r = ROLE_MAP[u.role]
              return (
                <tr key={u.user_id}>
                  <td className="strong">{u.email}</td>
                  <td><Badge variant={r.v}>{t(r.labelKey)}</Badge></td>
                  <td>{u.status === 'active' ? <Badge variant="ok" dot>{t('users.status.active')}</Badge> : <Badge variant="warn" dot>{t('users.status.invited')}</Badge>}</td>
                  <td className="muted">—</td>
                  <td className="row-act">
                    <Button variant="ghost-danger" size="sm" onClick={() => setDeleteTarget(u)}>
                      <Icon name="trash" size="sm" />{t('users.delete')}
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
