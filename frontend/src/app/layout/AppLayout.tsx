import { useState } from 'react'
import { NavLink, Link, Outlet, useNavigate, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from '@/shared/i18n/use-translation'
import { clearToken, isAdmin } from '@/shared/api/client'
import { getCurrentUser } from '@/shared/api/endpoints'
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

// Mobile bottom-tab bar (<=820px): the 4 primary day-to-day destinations — overview
// → reconcile (core, badge) → confirm the incoming transaction → dunning. Everything
// else (settings/receivables/import/help/admin) folds into the Menu sheet, which
// reuses the exact same NAV/ADMIN_NAV definitions. `nav.tab.bankTransactions` is a
// tab-length label (取引 / Transactions) so 5 tabs never wrap on iPhone 13 (390px).
const MOBILE_TABS: { to: string; labelKey: MessageKey; icon: string; end?: boolean }[] = [
  { to: '/admin', labelKey: 'nav.dashboard', icon: 'grid', end: true },
  { to: '/admin/reconciliation', labelKey: 'nav.reconciliation', icon: 'reconcile' },
  { to: '/admin/bank-transactions', labelKey: 'nav.tab.bankTransactions', icon: 'table' },
  { to: '/admin/dunning', labelKey: 'nav.dunning', icon: 'bell' },
]
const RECON_BADGE = NAV.find(n => n.to === '/admin/reconciliation')?.badge

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

// The business + admin nav sections, shared by the desktop sidebar and the mobile
// menu sheet so both stay in lock-step (single source of truth = NAV/ADMIN_NAV).
function NavSections({ t, adminNav }: { t: (k: MessageKey) => string; adminNav: NavEntry[] }) {
  return (
    <>
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
    </>
  )
}

export default function AppLayout() {
  const { t, locale, setLocale } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const admin = isAdmin()
  const adminNav = ADMIN_NAV.filter(item => !item.adminOnly || admin)

  // Mobile menu sheet. Two flags so the sheet can animate in/out: `sheetMounted`
  // drives the `hidden` attribute (in/out of the layer — the scrim must not be
  // present while closed or it would swallow all taps), `sheetOpen` drives the
  // `.open` class (transform/opacity transition). Never set display:none via a
  // class — that kills the transition (documented pitfall in the design handoff).
  const [sheetMounted, setSheetMounted] = useState(false)
  const [sheetOpen, setSheetOpen] = useState(false)
  function openSheet() {
    setSheetMounted(true)
    requestAnimationFrame(() => setSheetOpen(true))
  }
  function closeSheet() {
    setSheetOpen(false)
    setTimeout(() => setSheetMounted(false), 240)
  }
  // The Menu tab reads active whenever the sheet is open or we're on a route that
  // isn't one of the 4 primary tabs (settings/receivables/import/help/admin…).
  const path = location.pathname
  const primaryActive = MOBILE_TABS.some(tb => (tb.end ? path === tb.to : path.startsWith(tb.to)))
  const menuActive = sheetMounted || !primaryActive

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
          <NavSections t={t} adminNav={adminNav} />
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
          <Link to="/admin" className="mbrand" aria-label="NeNe Clear">
            <LogoMark height={24} className="logo-mark" />
            <b>NeNe Clear</b>
          </Link>
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

      {/* Mobile bottom-tab bar (<=820px, hidden on desktop via CSS). */}
      <nav className="mnav" aria-label={t('crumb.menu')}>
        {MOBILE_TABS.map(tb => (
          <NavLink
            key={tb.to}
            to={tb.to}
            end={tb.end}
            className={({ isActive }) => ['mtab', isActive ? 'active' : ''].filter(Boolean).join(' ')}
          >
            <Icon name={tb.icon} />
            {tb.to === '/admin/reconciliation' && RECON_BADGE !== undefined && (
              <span className="mtab-badge">{RECON_BADGE}</span>
            )}
            <span>{t(tb.labelKey)}</span>
          </NavLink>
        ))}
        <button
          type="button"
          className={['mtab', menuActive ? 'active' : ''].filter(Boolean).join(' ')}
          onClick={openSheet}
          aria-haspopup="dialog"
          aria-expanded={sheetOpen}
        >
          <span className="mdots"><i /><i /><i /></span>
          <span>{t('crumb.menu')}</span>
        </button>
      </nav>

      {/* Menu sheet — folds the full nav (business + admin) + user/logout footer. */}
      <div className={['msheet', sheetOpen ? 'open' : ''].filter(Boolean).join(' ')} hidden={!sheetMounted}>
        <div className="msheet-scrim" onClick={closeSheet} />
        <div className="msheet-panel" role="dialog" aria-modal="true" aria-label={t('crumb.menu')}>
          <div className="msheet-grab" />
          <nav className="msheet-nav" onClick={e => { if ((e.target as HTMLElement).closest('a')) closeSheet() }}>
            <NavSections t={t} adminNav={adminNav} />
          </nav>
          <div className="msheet-foot">
            <div className="avatar">AD</div>
            <div className="who">
              <b>{t('user.role.admin')}</b>
              <span>admin@example.com</span>
            </div>
            <button className="logout" title={t('nav.logout')} aria-label={t('nav.logout')} onClick={handleLogout}>
              <Icon name="logout" />
            </button>
          </div>
        </div>
      </div>

      {/* Global keyboard dispatcher — mounted once, inside the authenticated shell. */}
      <KeyboardShortcuts />
    </div>
  )
}
