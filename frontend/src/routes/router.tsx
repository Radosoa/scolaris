import type { ComponentType } from 'react'
import { createBrowserRouter, Navigate, type RouteObject } from 'react-router'
import { FullPageLoader } from '@/components/FullPageLoader'
import { AppLayout } from '@/components/layout/AppLayout'
import { NAVIGATION } from '@/config/navigation'
import { LoginPage } from '@/features/auth/LoginPage'
import { ComingSoonPage } from '@/pages/ComingSoonPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { ForbiddenPage, NotFoundPage } from '@/pages/ErrorPages'
import { GuestRoute, ProtectedRoute, RoleRoute } from './guards'

type Chargeur = () => Promise<{ Component: ComponentType }>

/** Écrans livrés, chargés à la demande (un fichier JS par écran). */
const ECRANS: Record<string, Chargeur> = {
  '/structure/annees': () => import('@/features/structure/pages/AnneesPage').then((m) => ({ Component: m.AnneesPage })),
  '/structure/classes': () => import('@/features/structure/pages/ClassesPage').then((m) => ({ Component: m.ClassesPage })),
  '/structure/matieres': () =>
    import('@/features/structure/pages/MatieresPage').then((m) => ({ Component: m.MatieresPage })),
}

/** Sous-pages, protégées par les mêmes rôles que leur rubrique parente. */
const SOUS_PAGES: Record<string, { path: string; lazy: Chargeur }[]> = {
  '/structure/annees': [
    {
      path: '/structure/annees/:id',
      lazy: () => import('@/features/structure/pages/AnneeDetailPage').then((m) => ({ Component: m.AnneeDetailPage })),
    },
  ],
}

// Chaque rubrique de la navigation est protégée par les rôles qui y ont accès.
// Les rubriques des phases suivantes affichent ComingSoonPage en attendant leur écran.
const rubriques: RouteObject[] = NAVIGATION.flatMap((groupe) => groupe.items)
  .filter((item) => item.path !== '/tableau-de-bord')
  .map((item) => ({
    element: <RoleRoute roles={item.roles} />,
    children: [
      ECRANS[item.path]
        ? { path: item.path, lazy: ECRANS[item.path] }
        : { path: item.path, element: <ComingSoonPage title={item.label} /> },
      ...(SOUS_PAGES[item.path] ?? []),
    ],
  }))

export const router = createBrowserRouter([
  {
    element: <GuestRoute />,
    children: [{ path: '/connexion', element: <LoginPage /> }],
  },
  {
    element: <ProtectedRoute />,
    // Affiché pendant le chargement d'un écran à la demande lors du premier rendu
    hydrateFallbackElement: <FullPageLoader />,
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
