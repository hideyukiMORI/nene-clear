import { NavLink, Link, Outlet, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from '@/shared/i18n/use-translation'
import { clearToken, isAdmin } from '@/shared/api/client'
import { getCurrentUser } from '@/api/endpoints'
import { Icon } from '@/shared/ui/icon'
import { LogoMark } from '@/shared/ui/logo-mark'
import { KeyboardShortcuts, openShortcutsOverlay } from '@/components/keyboard'
import type { MessageKey } from '@/shared/i18n/messages'

type NavEntry = { to: string; labelKey: MessageKey; icon: string; end?: boolean; badge?: number; adminOnly?: boolean }

const NAV: NavEntry[] = [
  { to: '/admin', labelKey: 'nav.dashboard', icon: 'grid', end: true },
  { to: '/admin/bank-import', labelKey: 'nav.bankImport', icon: 'import' },
  { to: '/admin/bank-transactions', labelKey: 'bankTransaction.title', icon: 'table' },
  { to: '/admin/reconciliation', labelKey: 'nav.reconciliation', icon: 'reconcile', badge: 12 },
  { to: '/admin/client-credits', labelKey: 'nav.clientCredits', icon: 'credit' },
  { to: '/admin/manual-receivables', labelKey: 'nav.manualReceivables', icon: 'doc' },
  { to: '/admin/dunning', labelKey: 'nav.dunning', icon: 'bell' },
  { to: '/admin/help', labelKey: 'nav.help', icon: 'info' },
]

// Every entry here is administrators-only: the backend gates settings behind
// manage_clear_settings and users/audit behind manage_users, so non-admins would
// only ever see 403-empty screens. Hide them from the nav and guard the routes
// (RequireAdmin) instead of showing dead links.
const ADMIN_NAV: NavEntry[] = [
  { to: '/admin/settings', labelKey: 'nav.settings', icon: 'gear', adminOnly: true },
  { to: '/admin/users', labelKey: 'nav.users', icon: 'users', adminOnly: true },
  { to: '/admin/audit-log', labelKey: 'nav.auditLog', icon: 'clock', adminOnly: true },
]

function NavItem({ to, icon, label, badge, end }: { to: string; icon: string; label: string; badge?: number; end?: boolean }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) => ['nav-item', isActive ? 'active' : ''].filter(Boolean).join(' ')}
    >
      <Icon name={icon} />
      {label}
      {badge !== undefined && <span className="nav-badge">{badge}</span>}
    </NavLink>
  )
}

export default function AppLayout() {
  const { t, locale, setLocale } = useTranslation()
  const navigate = useNavigate()
  const admin = isAdmin()
  const adminNav = ADMIN_NAV.filter(item => !item.adminOnly || admin)

  // Load the session/org context (incl. the org's fiscal year-end month) once
  // per session and hold it in the query cache. Page content waits for it so
  // pages can read the fiscal-year default synchronously at mount — no
  // per-page effect. Settings changes invalidate ['auth','me'] to refresh it.
  const meQ = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: ({ signal }) => getCurrentUser(signal),
    staleTime: 5 * 60_000,
    retry: false,
  })

  function handleLogout() {
    // Clearing the token flips the reactive auth store → the login screen
    // renders in place. Reset the URL to the app root so the next login lands
    // on the dashboard rather than whatever deep route we were on.
    navigate('/admin', { replace: true })
    clearToken()
  }

  return (
    <div className="app">
      <aside className="side">
        <div className="side-brand">
          <LogoMark height={26} className="logo-mark" />
          <div>
            <b>NeNe Clear</b>
            <small>AR Suite</small>
          </div>
        </div>
        <nav className="side-nav">
          <div className="side-sect">{t('nav.section.business')}</div>
          {NAV.map(item => (
            <NavItem key={item.to} to={item.to} icon={item.icon} label={t(item.labelKey)} badge={item.badge} end={item.end} />
          ))}
          {adminNav.length > 0 && (
            <>
              <div className="side-sect">{t('nav.section.admin')}</div>
              {adminNav.map(item => (
                <NavItem key={item.to} to={item.to} icon={item.icon} label={t(item.labelKey)} />
              ))}
            </>
          )}
        </nav>
        <div className="side-foot">
          <div className="side-user">
            <div className="avatar">AD</div>
            <div className="who">
              <b>{t('user.role.admin')}</b>
              <span>admin@example.com</span>
            </div>
          </div>
          <Link to="/admin/help#disclaimer" className="side-legal">{t('nav.disclaimer')}</Link>
        </div>
      </aside>

      <div className="main">
        <header className="topbar">
          <nav className="crumb">
            <span>NeNe Clear</span>
            <svg className="ic" style={{ width: 14, height: 14 }}><use href="#i-chev-r" /></svg>
            <b>{t('crumb.menu')}</b>
          </nav>
          <div className="topbar-r">
            <div className="lang">
              <button className={locale === 'ja' ? 'on' : ''} onClick={() => setLocale('ja')}>JA</button>
              <button className={locale === 'en' ? 'on' : ''} onClick={() => setLocale('en')}>EN</button>
            </div>
            <button
              className="iconbtn"
              title={t('shortcuts.open')}
              aria-label={t('shortcuts.open')}
              onClick={() => openShortcutsOverlay()}
            >
              <kbd className="kbd" aria-hidden="true">?</kbd>
            </button>
            <span className="sep" />
            <button className="iconbtn" title={t('nav.logout')} onClick={handleLogout}>
              <Icon name="logout" />
            </button>
          </div>
        </header>
        <div className="scroll">
          <div className="page">
            {meQ.isFetched
              ? <Outlet />
              : <p className="muted" style={{ padding: 24 }}>{t('common.loading')}</p>}
          </div>
        </div>
      </div>

      {/* Global keyboard dispatcher — mounted once, inside the authenticated shell. */}
      <KeyboardShortcuts />
    </div>
  )
}
