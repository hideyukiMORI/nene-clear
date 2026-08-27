import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listUsers, createUser, deleteUser } from '@/shared/api/endpoints'
import type { User } from '@/entities/user'
import { Button } from '@/shared/ui/button'
import { Card } from '@hideyukimori/nene2-ui'
import { DataTable, TableStateRow } from '@/shared/ui/data-table'
import { Icon } from '@/shared/ui/icon'
import { Modal } from '@/shared/ui/modal'
import { Notice } from '@/shared/ui/notice'
import { PageHead } from '@/shared/ui/page-head'
import { StatusBadge } from '@/shared/ui/status-badge'
import type { StatusMeta } from '@/shared/ui/status-badge'
import { useTranslation } from '@/shared/i18n/use-translation'
import { useRowCursor } from '@/components/keyboard'

type Role = 'admin' | 'member' | 'viewer'

const ROLE_MAP: Record<User['role'], StatusMeta> = {
  superadmin: { v: 'info', labelKey: 'users.role.superadmin' },
  admin:  { v: 'info', labelKey: 'users.role.admin' },
  member: { v: 'neut', labelKey: 'users.role.member' },
  viewer: { v: 'neut', labelKey: 'users.role.viewer' },
}

const STATUS_MAP: Record<User['status'], StatusMeta> = {
  active:  { v: 'ok',   labelKey: 'users.status.active' },
  invited: { v: 'warn', labelKey: 'users.status.invited' },
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
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="primary" disabled={!email.trim() || mut.isPending} onClick={() => mut.mutate()}><Icon decorative name="send" />{mut.isPending ? t('common.sending') : t('users.inviteSubmit')}</Button></>}
    >
      <div className="field">
        <label>{t('users.email')}</label>
        <div className="inp-icon"><Icon decorative name="mail" /><input className="inp" type="email" placeholder="user@example.com" style={{ paddingLeft: 34 }} value={email} onChange={e => setEmail(e.target.value)} /></div>
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
      footer={<><Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button><Button variant="danger" disabled={mut.isPending} onClick={() => mut.mutate()}><Icon decorative name="trash" />{mut.isPending ? t('common.processing') : t('users.delete')}</Button></>}
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

  // Row cursor (j/k); Enter/o opens the delete confirmation for the cursored user.
  const rows = usersQ.data?.items ?? []
  const cursor = useRowCursor(rows.length, (i) => {
    const u = rows[i]
    if (u) setDeleteTarget(u)
  })

  return (
    <>
      <PageHead
        title={t('users.title')}
        sub={t('users.subtitle')}
        actions={<Button variant="primary" onClick={() => setShowInvite(true)}><Icon decorative name="plus" />{t('users.invite')}</Button>}
      />

      <Card pad="none" className="min-w-0">
        <DataTable>
          <thead>
            <tr><th>{t('table.email')}</th><th>{t('table.role')}</th><th>{t('table.status')}</th><th>{t('table.lastLogin')}</th><th /></tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={5} loading={usersQ.isLoading} empty={usersQ.data?.items.length === 0} />
            {usersQ.data?.items.map((u, index) => (
              <tr key={u.user_id} data-kbd-row={index} className={cursor === index ? 'is-cursor' : undefined}>
                <td className="strong">{u.email}</td>
                <td><StatusBadge map={ROLE_MAP} value={u.role} /></td>
                <td><StatusBadge map={STATUS_MAP} value={u.status} dot /></td>
                <td className="muted">—</td>
                <td className="row-act">
                  <Button variant="ghost-danger" size="sm" onClick={() => setDeleteTarget(u)}>
                    <Icon decorative name="trash" size="sm" />{t('users.delete')}
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </DataTable>
      </Card>

      {showInvite && <InviteModal onClose={() => setShowInvite(false)} />}
      {deleteTarget && <DeleteModal user={deleteTarget} onClose={() => setDeleteTarget(null)} />}
    </>
  )
}
