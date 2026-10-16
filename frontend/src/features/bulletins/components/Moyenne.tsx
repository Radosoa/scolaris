import { cn } from '@/lib/utils'
import { formatMoyenne, tonMoyenne, type TonMention } from '../format'

const COULEURS: Record<TonMention, string> = {
  excellent: 'text-emerald-700 dark:text-emerald-400',
  bon: 'text-sky-700 dark:text-sky-400',
  moyen: 'text-amber-700 dark:text-amber-400',
  faible: 'text-red-700 dark:text-red-400',
}

/** Moyenne sur 20 colorée selon le niveau (vert ≥ 14, bleu ≥ 12, orange ≥ 10, rouge en dessous). */
export function Moyenne({ valeur, className }: { valeur: number | null | undefined; className?: string }) {
  const ton = tonMoyenne(valeur)

  return (
    <span className={cn('font-medium tabular-nums', ton ? COULEURS[ton] : 'text-muted-foreground', className)}>
      {formatMoyenne(valeur)}
    </span>
  )
}
