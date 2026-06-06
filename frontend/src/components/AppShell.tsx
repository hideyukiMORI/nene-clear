import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useTranslation } from '@/hooks/useTranslation'
import { clearToken, isAdmin } from '@/api/client'
import { Icon, LogoMark } from '@/components/ui'
import { KeyboardShortcuts, openShortcutsOverlay } from '@/components/keyboard'
import type { MessageKey } from '@/locales'

type NavEntry = { to: string; labelKey: MessageKey; icon: string; end?: boolean; badge?: number; adminOnly?: boolean }

const NAV: NavEntry[] = [
  { to: '/admin', labelKey: 'nav.dashboard', icon: 'grid', end: true },
  { to: '/admin/bank-import', labelKey: 'nav.bankImport', icon: 'import' },
  { to: '/admin/bank-transactions', labelKey: 'bankTransaction.title', icon: 'table' },
  { to: '/admin/reconciliation', labelKey: 'nav.reconciliation', icon: 'reconcile', badge: 12 },
  { to: '/admin/client-credits', labelKey: 'nav.clientCredits', icon: 'credit' },
  { to: '/admin/dunning', labelKey: 'nav.dunning', icon: 'bell' },
]

const ADMIN_NAV: NavEntry[] = [
  { to: '/admin/settings', labelKey: 'nav.settings', icon: 'gear' },
  { to: '/admin/users', labelKey: 'nav.users', icon: 'users' },
  // The audit log is administrators-only (backend gates it behind manage_users).
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

export default function AppShell() {
  const { t, locale, switchLocale } = useTranslation()
  const navigate = useNavigate()
  const admin = isAdmin()
  const adminNav = ADMIN_NAV.filter(item => !item.adminOnly || admin)

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
          <div className="side-sect">{t('nav.section.admin')}</div>
          {adminNav.map(item => (
            <NavItem key={item.to} to={item.to} icon={item.icon} label={t(item.labelKey)} />
          ))}
        </nav>
        <div className="side-foot">
          <div className="side-user">
            <div className="avatar">AD</div>
            <div className="who">
              <b>{t('user.role.admin')}</b>
              <span>admin@example.com</span>
            </div>
          </div>
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
              <button className={locale === 'ja' ? 'on' : ''} onClick={() => switchLocale('ja')}>JA</button>
              <button className={locale === 'en' ? 'on' : ''} onClick={() => switchLocale('en')}>EN</button>
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
            <Outlet />
          </div>
        </div>
      </div>

      {/* Global keyboard dispatcher — mounted once, inside the authenticated shell. */}
      <KeyboardShortcuts />
    </div>
  )
}
