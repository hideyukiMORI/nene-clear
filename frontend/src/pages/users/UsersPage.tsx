import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from '@/hooks/useTranslation'
import { listUsers, createUser, deleteUser } from '@/api/endpoints'
import type { User } from '@/types'

function RoleBadge({ role }: { role: User['role'] }) {
  const { t } = useTranslation()
  const styles: Record<User['role'], string> = {
    superadmin: 'bg-purple-100 text-purple-800',
    admin: 'bg-blue-100 text-blue-800',
    member: 'bg-gray-100 text-gray-700',
    viewer: 'bg-gray-100 text-gray-500',
  }
  const labels: Record<User['role'], string> = {
    superadmin: 'Superadmin',
    admin: t('users.role.admin'),
    member: t('users.role.member'),
    viewer: t('users.role.viewer'),
  }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[role]}`}>
      {labels[role]}
    </span>
  )
}

function StatusBadge({ status }: { status: User['status'] }) {
  const { t } = useTranslation()
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
      status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
    }`}>
      {status === 'active' ? t('users.status.active') : t('users.status.invited')}
    </span>
  )
}

export default function UsersPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()

  const [showInvite, setShowInvite] = useState(false)
  const [inviteEmail, setInviteEmail] = useState('')
  const [inviteRole, setInviteRole] = useState<'admin' | 'member' | 'viewer'>('member')
  const [inviteError, setInviteError] = useState<string | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<User | null>(null)

  const usersQuery = useQuery({
    queryKey: ['users'],
    queryFn: ({ signal }) => listUsers({ limit: 100 }, signal),
  })

  const inviteMutation = useMutation({
    mutationFn: () => createUser(inviteEmail, inviteRole),
    onSuccess: () => {
      setShowInvite(false)
      setInviteEmail('')
      setInviteRole('member')
      setInviteError(null)
      void qc.invalidateQueries({ queryKey: ['users'] })
    },
    onError: (err: Error) => {
      setInviteError(err.message)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (userId: number) => deleteUser(userId),
    onSuccess: () => {
      setDeleteTarget(null)
      void qc.invalidateQueries({ queryKey: ['users'] })
    },
  })

  function handleInvite(e: React.FormEvent) {
    e.preventDefault()
    setInviteError(null)
    if (!inviteEmail.trim()) {
      setInviteError('メールアドレスを入力してください')
      return
    }
    inviteMutation.mutate()
  }

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">{t('users.title')}</h1>
        <button
          onClick={() => setShowInvite(true)}
          className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          {t('users.invite')}
        </button>
      </div>

      {/* User table */}
      <div className="rounded-lg bg-white shadow-sm overflow-hidden">
        {usersQuery.isLoading && (
          <p className="px-6 py-4 text-sm text-gray-400">{t('common.loading')}</p>
        )}
        {usersQuery.isError && (
          <p className="px-6 py-4 text-sm text-red-600">{usersQuery.error.message}</p>
        )}
        {usersQuery.data && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                <th className="px-6 py-3">{t('users.email')}</th>
                <th className="px-6 py-3">{t('users.role')}</th>
                <th className="px-6 py-3">{t('users.status')}</th>
                <th className="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {usersQuery.data.items.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-6 py-4 text-center text-gray-400">
                    {t('common.noData')}
                  </td>
                </tr>
              )}
              {usersQuery.data.items.map((user, i) => (
                <tr
                  key={user.user_id}
                  className={i % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'}
                >
                  <td className="px-6 py-3 font-medium text-gray-800">{user.email}</td>
                  <td className="px-6 py-3">
                    <RoleBadge role={user.role} />
                  </td>
                  <td className="px-6 py-3">
                    <StatusBadge status={user.status} />
                  </td>
                  <td className="px-6 py-3">
                    <button
                      onClick={() => setDeleteTarget(user)}
                      className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                    >
                      {t('users.delete')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Invite modal */}
      {showInvite && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
            <h3 className="mb-4 text-base font-semibold text-gray-900">{t('users.invite')}</h3>
            <form onSubmit={handleInvite} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{t('users.email')}</label>
                <input
                  type="email"
                  value={inviteEmail}
                  onChange={e => setInviteEmail(e.target.value)}
                  className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">{t('users.role')}</label>
                <select
                  value={inviteRole}
                  onChange={e => setInviteRole(e.target.value as typeof inviteRole)}
                  className="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                >
                  <option value="admin">{t('users.role.admin')}</option>
                  <option value="member">{t('users.role.member')}</option>
                  <option value="viewer">{t('users.role.viewer')}</option>
                </select>
              </div>
              {inviteError && (
                <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{inviteError}</p>
              )}
              <div className="flex gap-3 justify-end">
                <button
                  type="button"
                  onClick={() => { setShowInvite(false); setInviteError(null) }}
                  className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                  {t('common.cancel')}
                </button>
                <button
                  type="submit"
                  disabled={inviteMutation.isPending}
                  className="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                  {inviteMutation.isPending ? t('common.loading') : t('users.invite')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete confirm modal */}
      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
            <h3 className="mb-2 text-base font-semibold text-gray-900">{t('users.confirmDelete')}</h3>
            <p className="mb-4 text-sm text-gray-600">{deleteTarget.email}</p>
            {deleteMutation.isError && (
              <p className="mb-3 text-sm text-red-600">{deleteMutation.error.message}</p>
            )}
            <div className="flex gap-3 justify-end">
              <button
                onClick={() => setDeleteTarget(null)}
                className="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                {t('common.cancel')}
              </button>
              <button
                onClick={() => deleteMutation.mutate(deleteTarget.user_id)}
                disabled={deleteMutation.isPending}
                className="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
              >
                {deleteMutation.isPending ? t('common.loading') : t('users.delete')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
