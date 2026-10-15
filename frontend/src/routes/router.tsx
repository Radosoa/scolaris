import { createBrowserRouter, Navigate } from 'react-router'
import { AppLayout } from '@/components/layout/AppLayout'
import { NAVIGATION } from '@/config/navigation'
import { LoginPage } from '@/features/auth/LoginPage'
import { ComingSoonPage } from '@/pages/ComingSoonPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { ForbiddenPage, NotFoundPage } from '@/pages/ErrorPages'
import { GuestRoute, ProtectedRoute, RoleRoute } from './guards'

// Chaque rubrique de la navigation est protégée par les rôles qui y ont accès.
// Les écrans des phases suivantes remplaceront progressivement ComingSoonPage.
const rubriques = NAVIGATION.flatMap((groupe) => groupe.items)
  .filter((item) => item.path !== '/tableau-de-bord')
  .map((item) => ({
    element: <RoleRoute roles={item.roles} />,
    children: [{ path: item.path, element: <ComingSoonPage title={item.label} /> }],
  }))

export const router = createBrowserRouter([
  {
    element: <GuestRoute />,
    children: [{ path: '/connexion', element: <LoginPage /> }],
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <Navigate to="/tableau-de-bord" replace /> },
          { path: '/tableau-de-bord', element: <DashboardPage /> },
          ...rubriques,
          { path: '/acces-refuse', element: <ForbiddenPage /> },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])
