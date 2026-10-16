import { cn } from '@/lib/utils'

/** « Claire Martin » → « CM » */
function initiales(nom: string): string {
  return nom
    .split(/\s+/)
    .slice(0, 2)
    .map((partie) => partie[0]?.toUpperCase() ?? '')
    .join('')
}

/** Pastille aux initiales, dans le dégradé de la marque. */
export function AvatarUtilisateur({ nom, className }: { nom: string; className?: string }) {
  return (
    <span
      aria-hidden
      className={cn(
        'flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-xs font-semibold text-white ring-2 ring-white dark:ring-sidebar',
        className,
      )}
    >
      {initiales(nom)}
    </span>
  )
}
