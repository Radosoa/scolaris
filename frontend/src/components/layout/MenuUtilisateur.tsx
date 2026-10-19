import { LogOut } from 'lucide-react'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuth } from '@/features/auth/auth-context'
import { ROLE_LIBELLES } from '@/types/auth'
import { AvatarUtilisateur } from './AvatarUtilisateur'

/** Avatar de l'utilisateur connecté dans l'en-tête ; son menu donne l'identité et la déconnexion. */
export function MenuUtilisateur() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  if (!user) return null

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
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label="Mon compte"
        title="Mon compte"
        className="rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
      >
        <AvatarUtilisateur nom={user.name} className="ring-background transition-opacity hover:opacity-90" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-60">
        <DropdownMenuLabel className="flex items-center gap-3 py-2 font-normal">
          <AvatarUtilisateur nom={user.name} className="ring-0" />
          <span className="min-w-0 leading-tight">
            <span className="block truncate text-sm font-medium">{user.name}</span>
            <span className="block truncate text-xs text-muted-foreground">{user.roles.map((role) => ROLE_LIBELLES[role]).join(', ')}</span>
          </span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem variant="destructive" onSelect={seDeconnecter}>
          <LogOut />
          Se déconnecter
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
