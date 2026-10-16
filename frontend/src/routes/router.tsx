import type { ComponentType } from 'react'
import { createBrowserRouter, Navigate, type RouteObject } from 'react-router'
import { FullPageLoader } from '@/components/FullPageLoader'
import { AppLayout } from '@/components/layout/AppLayout'
import { NAVIGATION } from '@/config/navigation'
import { LoginPage } from '@/features/auth/LoginPage'
import { ComingSoonPage } from '@/pages/ComingSoonPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { ForbiddenPage, NotFoundPage } from '@/pages/ErrorPages'
import type { Role } from '@/types/auth'
import { GuestRoute, ProtectedRoute, RoleRoute } from './guards'

type Chargeur = () => Promise<{ Component: ComponentType }>

/** Écrans livrés, chargés à la demande (un fichier JS par écran). */
const ECRANS: Record<string, Chargeur> = {
  '/structure/annees': () => import('@/features/structure/pages/AnneesPage').then((m) => ({ Component: m.AnneesPage })),
  '/structure/classes': () => import('@/features/structure/pages/ClassesPage').then((m) => ({ Component: m.ClassesPage })),
  '/structure/matieres': () =>
    import('@/features/structure/pages/MatieresPage').then((m) => ({ Component: m.MatieresPage })),
  '/eleves': () => import('@/features/personnes/pages/ElevesPage').then((m) => ({ Component: m.ElevesPage })),
  '/inscriptions': () =>
    import('@/features/personnes/pages/InscriptionsPage').then((m) => ({ Component: m.InscriptionsPage })),
  '/parents': () => import('@/features/personnes/pages/ParentsPage').then((m) => ({ Component: m.ParentsPage })),
  '/enseignants': () =>
    import('@/features/personnes/pages/EnseignantsPage').then((m) => ({ Component: m.EnseignantsPage })),
  '/affectations': () =>
    import('@/features/personnes/pages/AffectationsPage').then((m) => ({ Component: m.AffectationsPage })),
  '/mes-enfants': () => import('@/features/personnes/pages/MesEnfantsPage').then((m) => ({ Component: m.MesEnfantsPage })),
  '/notes': () => import('@/features/notes/pages/NotesPage').then((m) => ({ Component: m.NotesPage })),
  '/bulletins': () => import('@/features/bulletins/pages/BulletinsPage').then((m) => ({ Component: m.BulletinsPage })),
  '/appel': () => import('@/features/vie-scolaire/pages/AppelPage').then((m) => ({ Component: m.AppelPage })),
  '/absences': () => import('@/features/vie-scolaire/pages/AbsencesPage').then((m) => ({ Component: m.AbsencesPage })),
  '/emploi-du-temps': () =>
    import('@/features/vie-scolaire/pages/EmploiDuTempsPage').then((m) => ({ Component: m.EmploiDuTempsPage })),
}

interface SousPage {
  path: string
  lazy: Chargeur
  /** Rôles autorisés, si différents de ceux de la rubrique parente */
  roles?: Role[]
}

const SOUS_PAGES: Record<string, SousPage[]> = {
  '/bulletins': [
    {
      // Le secrétariat y accède depuis la fiche élève ; l'API ne sert que les bulletins autorisés (403 sinon)
      path: '/bulletins/:id',
      lazy: () => import('@/features/bulletins/pages/BulletinPage').then((m) => ({ Component: m.BulletinPage })),
      roles: ['admin', 'secretariat', 'professeur', 'parent', 'eleve'],
    },
  ],
  '/notes': [
    {
      path: '/notes/evaluations/:id',
      lazy: () => import('@/features/notes/pages/GrilleNotesPage').then((m) => ({ Component: m.GrilleNotesPage })),
      roles: ['admin', 'professeur'],
    },
  ],
  '/structure/annees': [
    {
      path: '/structure/annees/:id',
      lazy: () => import('@/features/structure/pages/AnneeDetailPage').then((m) => ({ Component: m.AnneeDetailPage })),
    },
  ],
  '/eleves': [
    {
      // Un parent ouvre la fiche de son enfant, un élève la sienne : l'API refuse les autres (403)
      path: '/eleves/:id',
      lazy: () => import('@/features/personnes/pages/EleveFichePage').then((m) => ({ Component: m.EleveFichePage })),
      roles: ['admin', 'secretariat', 'professeur', 'parent', 'eleve'],
    },
  ],
}

// Chaque rubrique de la navigation est protégée par les rôles qui y ont accès.
// Les rubriques des phases suivantes affichent ComingSoonPage en attendant leur écran.
const rubriques: RouteObject[] = NAVIGATION.flatMap((groupe) => groupe.items)
  .filter((item) => item.path !== '/tableau-de-bord')
  .flatMap((item) => [
    {
      element: <RoleRoute roles={item.roles} />,
      children: [
        ECRANS[item.path]
          ? { path: item.path, lazy: ECRANS[item.path] }
          : { path: item.path, element: <ComingSoonPage title={item.label} /> },
      ],
    },
    ...(SOUS_PAGES[item.path] ?? []).map(({ roles, ...route }) => ({
      element: <RoleRoute roles={roles ?? item.roles} />,
      children: [route],
    })),
  ])

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
