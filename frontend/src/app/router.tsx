import { createBrowserRouter, Navigate } from 'react-router-dom'
import { isAuthenticated } from '@/api/client'
import AppShell from '@/components/AppShell'
import LoginPage from '@/pages/login/LoginPage'
import DashboardPage from '@/pages/dashboard/DashboardPage'
import BankImportPage from '@/pages/bank-import/BankImportPage'
import BankTransactionsPage from '@/pages/bank-transactions/BankTransactionsPage'
import ReconciliationPage from '@/pages/reconciliation/ReconciliationPage'
import ClientCreditsPage from '@/pages/client-credits/ClientCreditsPage'
import DunningPage from '@/pages/dunning/DunningPage'
import SettingsPage from '@/pages/settings/SettingsPage'
import UsersPage from '@/pages/users/UsersPage'
import AuditLogPage from '@/pages/audit/AuditLogPage'

function RequireAuth({ children }: { children: React.ReactNode }) {
  if (!isAuthenticated()) {
    return <Navigate to="/login" replace />
  }
  return <>{children}</>
}

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/admin',
    element: (
      <RequireAuth>
        <AppShell />
      </RequireAuth>
    ),
    children: [
      { index: true, element: <DashboardPage /> },
      { path: 'bank-import', element: <BankImportPage /> },
      { path: 'bank-transactions', element: <BankTransactionsPage /> },
      { path: 'reconciliation', element: <ReconciliationPage /> },
      { path: 'client-credits', element: <ClientCreditsPage /> },
      { path: 'dunning', element: <DunningPage /> },
      { path: 'settings', element: <SettingsPage /> },
      { path: 'users', element: <UsersPage /> },
      { path: 'audit-log', element: <AuditLogPage /> },
    ],
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
