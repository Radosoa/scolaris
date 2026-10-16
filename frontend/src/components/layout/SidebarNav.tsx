import { GraduationCap, LogOut } from 'lucide-react'
import { NavLink, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { navigationPour } from '@/config/navigation'
import { useAuth } from '@/features/auth/auth-context'
import { cn } from '@/lib/utils'
import { ROLE_LIBELLES } from '@/types/auth'
import { AvatarUtilisateur } from './AvatarUtilisateur'

/** Contenu de la sidebar, partagé entre la version bureau et le tiroir mobile. */
export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const groupes = navigationPour(user?.roles ?? [])

  const seDeconnecter = async () => {
    try {
      await logout()
    } catch {
      // La session locale est purgée même si l'appel échoue
    }
    toast.success('Vous êtes déconnecté.')
    navigate('/connexion', { replace: true })
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex h-16 items-center gap-2.5 px-5">
        <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/25">
          <GraduationCap className="size-5" />
        </div>
        <div className="leading-tight">
          <p className="font-semibold tracking-tight">Scolaris</p>
          <p className="text-[11px] text-muted-foreground">Gestion scolaire</p>
        </div>
      </div>

      <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Navigation principale">
        {groupes.map((groupe, index) => (
          <div key={groupe.label ?? index} className="space-y-0.5">
            {groupe.label && (
              <p className="px-3 pb-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground/80 uppercase">{groupe.label}</p>
            )}
            {groupe.items.map((item) => (
              <NavLink
                key={item.path}
                to={item.path}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                    isActive
                      ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200'
                      : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                  )
                }
              >
                {({ isActive }) => (
                  <>
                    {isActive && <span aria-hidden className="absolute top-1/2 -left-3 h-5 w-1 -translate-y-1/2 rounded-r-full bg-indigo-600 dark:bg-indigo-400" />}
                    <item.icon
                      className={cn(
                        'size-4 shrink-0 transition-colors',
                        isActive ? 'text-indigo-600 dark:text-indigo-300' : 'text-muted-foreground group-hover:text-foreground',
                      )}
                    />
                    {item.label}
                  </>
                )}
              </NavLink>
            ))}
          </div>
        ))}
      </nav>

      {user && (
        <div className="border-t p-3">
          <div className="flex items-center gap-3 rounded-xl bg-sidebar-accent/60 p-2.5">
            <AvatarUtilisateur nom={user.name} />
            <div className="min-w-0 flex-1 leading-tight">
              <p className="truncate text-sm font-medium">{user.name}</p>
              <p className="truncate text-xs text-muted-foreground">{user.roles.map((role) => ROLE_LIBELLES[role]).join(', ')}</p>
            </div>
            <button
              type="button"
              onClick={seDeconnecter}
              aria-label="Se déconnecter"
              title="Se déconnecter"
              className="flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-background hover:text-destructive"
            >
              <LogOut className="size-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
