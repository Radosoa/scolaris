import { MoreHorizontal } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export interface RowAction {
  label: string
  icon: LucideIcon
  onSelect: () => void
  destructive?: boolean
  hidden?: boolean
}

/** Menu « ⋯ » d'actions sur une ligne de tableau. Les actions destructives sont séparées en bas. */
export function RowActions({ actions, label }: { actions: RowAction[]; label: string }) {
  const visibles = actions.filter((action) => !action.hidden)
  const normales = visibles.filter((action) => !action.destructive)
  const destructives = visibles.filter((action) => action.destructive)

  if (visibles.length === 0) return null

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={`Actions pour ${label}`}>
          <MoreHorizontal />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {normales.map((action) => (
          <DropdownMenuItem key={action.label} onSelect={action.onSelect}>
            <action.icon />
            {action.label}
          </DropdownMenuItem>
        ))}
        {normales.length > 0 && destructives.length > 0 && <DropdownMenuSeparator />}
        {destructives.map((action) => (
          <DropdownMenuItem key={action.label} variant="destructive" onSelect={action.onSelect}>
            <action.icon />
            {action.label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
