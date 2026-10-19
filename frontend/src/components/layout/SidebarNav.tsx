import { GraduationCap } from 'lucide-react'
import { NavLink } from 'react-router'
import { navigationPour } from '@/config/navigation'
import { useAuth } from '@/features/auth/auth-context'
import { useEcole } from '@/features/etablissement/api'
import { cn } from '@/lib/utils'

/** Contenu de la sidebar, partagé entre la version bureau et le tiroir mobile. */
export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const { user } = useAuth()
  const groupes = navigationPour(user?.roles ?? [])
  const { data: ecole } = useEcole()

  return (
    <div className="flex h-full flex-col">
      <div className="flex h-16 items-center gap-2.5 px-5">
        <div className="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/25">
          <GraduationCap className="size-5" />
        </div>
        <div className="leading-tight">
          <p className="font-semibold tracking-tight">Scolaris</p>
          <p className="max-w-40 truncate text-[11px] text-muted-foreground" title={ecole?.nom}>
            {ecole?.nom ?? 'Gestion scolaire'}
          </p>
        </div>
      </div>

      <nav className="barre-invisible flex-1 space-y-4 overflow-y-auto px-3 pt-2 pb-4" aria-label="Navigation principale">
        {groupes.map((groupe, index) => (
          <div key={groupe.label ?? index} className="space-y-0.5">
            {groupe.label && (
              <p className="px-3 pb-1 text-[11px] font-semibold tracking-wider text-muted-foreground/80 uppercase">{groupe.label}</p>
            )}
            {groupe.items.map((item) => (
              <NavLink
                key={item.path}
                to={item.path}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    'group relative flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm transition-colors',
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
    </div>
  )
}
