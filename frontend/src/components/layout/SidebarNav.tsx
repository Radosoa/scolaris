import { GraduationCap } from 'lucide-react'
import { NavLink } from 'react-router'
import { navigationPour } from '@/config/navigation'
import { useAuth } from '@/features/auth/auth-context'
import { cn } from '@/lib/utils'

/** Contenu de la sidebar, partagé entre la version bureau et le tiroir mobile. */
export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const { user } = useAuth()
  const groupes = navigationPour(user?.roles ?? [])

  return (
    <div className="flex h-full flex-col">
      <div className="flex h-14 items-center gap-2 border-b px-4">
        <div className="flex size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
          <GraduationCap className="size-4" />
        </div>
        <span className="font-semibold">Scolaris</span>
      </div>

      <nav className="flex-1 space-y-5 overflow-y-auto p-3" aria-label="Navigation principale">
        {groupes.map((groupe, index) => (
          <div key={groupe.label ?? index} className="space-y-1">
            {groupe.label && (
              <p className="px-2 pb-1 text-xs font-medium text-muted-foreground">{groupe.label}</p>
            )}
            {groupe.items.map((item) => (
              <NavLink
                key={item.path}
                to={item.path}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    'flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors',
                    isActive
                      ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                      : 'text-sidebar-foreground/80 hover:bg-sidebar-accent/60 hover:text-sidebar-accent-foreground',
                  )
                }
              >
                <item.icon className="size-4 shrink-0" />
                {item.label}
              </NavLink>
            ))}
          </div>
        ))}
      </nav>
    </div>
  )
}
