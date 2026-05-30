import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useTranslation } from '@/hooks/useTranslation'
import { clearToken } from '@/api/client'
import type { MessageKey } from '@/locales'

const navLabels: Array<{ to: string; labelKey: MessageKey; end?: boolean }> = [
  { to: '/admin', labelKey: 'nav.dashboard', end: true },
  { to: '/admin/bank-import', labelKey: 'nav.bankImport' },
  { to: '/admin/bank-transactions', labelKey: 'bankTransaction.title' },
  { to: '/admin/reconciliation', labelKey: 'nav.reconciliation' },
  { to: '/admin/dunning', labelKey: 'nav.dunning' },
  { to: '/admin/settings', labelKey: 'nav.settings' },
  { to: '/admin/users', labelKey: 'nav.users' },
]

export default function AppShell() {
  const { t, locale, switchLocale } = useTranslation()
  const navigate = useNavigate()

  function handleLogout() {
    clearToken()
    navigate('/login', { replace: true })
  }

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50">
      {/* Sidebar */}
      <aside className="flex w-56 shrink-0 flex-col bg-gray-900 text-white">
        {/* App title */}
        <div className="flex h-14 items-center px-4 text-lg font-bold tracking-wide border-b border-gray-700">
          {t('app.title')}
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto py-4 space-y-0.5 px-2">
          {navLabels.map(({ to, labelKey, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                [
                  'flex items-center rounded px-3 py-2 text-sm font-medium transition-colors',
                  isActive
                    ? 'bg-blue-600 text-white'
                    : 'text-gray-300 hover:bg-gray-700 hover:text-white',
                ].join(' ')
              }
            >
              {t(labelKey)}
            </NavLink>
          ))}
        </nav>
      </aside>

      {/* Main area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Top bar */}
        <header className="flex h-14 shrink-0 items-center justify-end gap-3 border-b border-gray-200 bg-white px-6">
          {/* Locale switcher */}
          <div className="flex items-center gap-1 text-sm">
            <button
              onClick={() => switchLocale('ja')}
              className={`px-2 py-1 rounded ${locale === 'ja' ? 'bg-gray-200 font-semibold' : 'hover:bg-gray-100'}`}
            >
              JA
            </button>
            <button
              onClick={() => switchLocale('en')}
              className={`px-2 py-1 rounded ${locale === 'en' ? 'bg-gray-200 font-semibold' : 'hover:bg-gray-100'}`}
            >
              EN
            </button>
          </div>

          {/* Logout */}
          <button
            onClick={handleLogout}
            className="rounded px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            {t('nav.logout')}
          </button>
        </header>

        {/* Content */}
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
