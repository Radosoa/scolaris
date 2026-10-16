import { Check, Monitor, Moon, Sun } from 'lucide-react'
import { useTheme } from '@/lib/theme-context'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'

const THEMES = [
  { valeur: 'light', libelle: 'Clair', icone: Sun },
  { valeur: 'dark', libelle: 'Sombre', icone: Moon },
  { valeur: 'system', libelle: 'Système', icone: Monitor },
] as const

/** Bascule clair / sombre / système (préférence mémorisée dans le navigateur). */
export function ChoixTheme({ className }: { className?: string }) {
  const { theme, resolvedTheme, setTheme } = useTheme()
  const Icone = resolvedTheme === 'dark' ? Moon : Sun

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label="Changer de thème" title="Thème" className={className}>
          <Icone />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-40">
        {THEMES.map(({ valeur, libelle, icone: IconeTheme }) => (
          <DropdownMenuItem key={valeur} onSelect={() => setTheme(valeur)}>
            <IconeTheme />
            {libelle}
            <Check className={cn('ml-auto', theme === valeur ? 'opacity-100' : 'opacity-0')} />
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
