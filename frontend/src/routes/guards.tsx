import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '@/features/auth/auth-context'
import type { Role } from '@/types/auth'
import { FullPageLoader } from '@/components/FullPageLoader'

/** Exige un utilisateur connecté, sinon redirige vers la connexion. */
export function ProtectedRoute() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') return <FullPageLoader />
  if (status === 'guest') {
    return <Navigate to="/connexion" replace state={{ from: location.pathname }} />
  }
  return <Outlet />
}

/** Exige au moins un des rôles donnés, sinon affiche la page 403. */
export function RoleRoute({ roles }: { roles: Role[] }) {
  const { hasRole } = useAuth()
  return hasRole(...roles) ? <Outlet /> : <Navigate to="/acces-refuse" replace />
}

/**
 * Pages publiques (connexion) : un utilisateur connecté est renvoyé vers la page
 * qu'il voulait ouvrir avant la connexion, ou vers le tableau de bord.
 */
export function GuestRoute() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') return <FullPageLoader />
  if (status === 'authenticated') {
    const from = (location.state as { from?: string } | null)?.from
    return <Navigate to={from ?? '/tableau-de-bord'} replace />
  }
  return <Outlet />
}
