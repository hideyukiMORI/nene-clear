import { useSyncExternalStore } from 'react'
import { createBrowserRouter, Navigate } from 'react-router-dom'
import { isAuthenticated, isAdmin, subscribeAuthChange } from '@/shared/api/client'
import AppLayout from '@/app/layout/AppLayout'
import LoginPage from '@/pages/login/LoginPage'
import AcceptInvitePage from '@/pages/accept-invite/AcceptInvitePage'
import DashboardPage from '@/pages/dashboard/DashboardPage'
import BankImportPage from '@/pages/bank-import/BankImportPage'
import BankTransactionsPage from '@/pages/bank-transactions/BankTransactionsPage'
import ReconciliationPage from '@/pages/reconciliation/ReconciliationPage'
import ClientCreditsPage from '@/pages/client-credits/ClientCreditsPage'
import ManualReceivablesPage from '@/pages/manual-receivables/ManualReceivablesPage'
import DunningPage from '@/pages/dunning/DunningPage'
import SettingsPage from '@/pages/settings/SettingsPage'
import UsersPage from '@/pages/users/UsersPage'
import AuditLogPage from '@/pages/audit/AuditLogPage'
import HelpPage from '@/pages/help/HelpPage'

/**
 * Auth shell. Subscribes to the reactive token store so an expired/cleared
 * session swaps the app for the login screen *in place* — same URL, no blank
 * page, no hard redirect. Logging back in flips the store and reveals the route
 * the user was on. (Honours the secure behaviour: the 401 handler clears the
 * token; this just renders the consequence.)
 */
function RequireAuth({ children }: { children: React.ReactNode }) {
  const authed = useSyncExternalStore(subscribeAuthChange, isAuthenticated, isAuthenticated)
  if (!authed) {
    return <LoginPage />
  }
  return <>{children}</>
}

/** Administrators-only routes (e.g. the audit log). Non-admins are sent home. */
function RequireAdmin({ children }: { children: React.ReactNode }) {
  if (!isAdmin()) {
    return <Navigate to="/admin" replace />
  }
  return <>{children}</>
}

export const router = createBrowserRouter([
  {
    path: '/admin',
    element: (
      <RequireAuth>
        <AppLayout />
      </RequireAuth>
    ),
    children: [
      { index: true, element: <DashboardPage /> },
      { path: 'bank-import', element: <BankImportPage /> },
      { path: 'bank-transactions', element: <BankTransactionsPage /> },
      { path: 'reconciliation', element: <ReconciliationPage /> },
      { path: 'client-credits', element: <ClientCreditsPage /> },
      { path: 'manual-receivables', element: <ManualReceivablesPage /> },
      { path: 'dunning', element: <DunningPage /> },
      { path: 'settings', element: <RequireAdmin><SettingsPage /></RequireAdmin> },
      { path: 'users', element: <RequireAdmin><UsersPage /></RequireAdmin> },
      { path: 'audit-log', element: <RequireAdmin><AuditLogPage /></RequireAdmin> },
      { path: 'help', element: <HelpPage /> },
    ],
  },
  {
    // Public onboarding route reached from the invitation e-mail link. It lives
    // outside RequireAuth so an invitee with no session can set their password.
    path: '/accept-invite',
    element: <AcceptInvitePage />,
  },
  {
    path: '/',
    element: <Navigate to="/admin" replace />,
  },
  {
    path: '*',
    element: <Navigate to="/admin" replace />,
  },
])
