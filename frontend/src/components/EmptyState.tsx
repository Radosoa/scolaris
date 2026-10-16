import { Inbox, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface EmptyStateProps {
  titre: string
  description?: string
  icone?: LucideIcon
  action?: ReactNode
  className?: string
}

/** État vide illustré : pastille d'icône, message et action éventuelle. */
export function EmptyState({ titre, description, icone: Icone = Inbox, action, className }: EmptyStateProps) {
  return (
    <div className={cn('flex flex-col items-center justify-center px-6 py-12 text-center', className)}>
      <div className="relative mb-4">
        <div aria-hidden className="absolute inset-0 scale-150 rounded-full bg-indigo-100/60 blur-xl dark:bg-indigo-500/10" />
        <div className="relative flex size-12 items-center justify-center rounded-2xl border bg-background text-indigo-600 shadow-sm dark:text-indigo-300">
          <Icone className="size-5" />
        </div>
      </div>
      <p className="text-sm font-medium text-foreground">{titre}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
