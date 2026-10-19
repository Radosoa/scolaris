import { CalendarX2, ChevronRight, CircleCheck, FileCheck2, FileClock, Wallet, type LucideIcon } from 'lucide-react'
import { Link } from 'react-router'
import { formatEuros } from '@/features/finances/format'
import { cn } from '@/lib/utils'
import type { TableauDirection } from '../types'
import { Panneau } from './Elements'

interface Tache {
  icone: LucideIcon
  titre: string
  detail: string
  compte: number
  lien: string
  urgence: 'haute' | 'normale'
}

/** Liste des actions en attente pour la direction, de la plus urgente à la moins urgente. */
export function ATraiter({ donnees }: { donnees: TableauDirection }) {
  const { absenteisme, finances, bulletins } = donnees
  const periode = bulletins.periode?.libelle ?? ''

  const taches: Tache[] = [
    finances.en_retard > 0 && {
      icone: Wallet,
      titre: 'Familles en retard de paiement',
      detail: `${formatEuros(finances.retard)} exigibles non réglés`,
      compte: finances.en_retard,
      lien: '/paiements',
      urgence: 'haute' as const,
    },
    absenteisme.a_justifier > 0 && {
      icone: CalendarX2,
      titre: 'Absences à justifier',
      detail: 'Relevées lors des appels',
      compte: absenteisme.a_justifier,
      lien: '/absences',
      urgence: 'normale' as const,
    },
    bulletins.a_calculer.length > 0 && {
      icone: FileClock,
      titre: `Bulletins à calculer · ${periode}`,
      detail: bulletins.a_calculer.join(', '),
      compte: bulletins.a_calculer.length,
      lien: '/bulletins',
      urgence: 'normale' as const,
    },
    bulletins.a_valider.length > 0 && {
      icone: FileCheck2,
      titre: `Bulletins à valider · ${periode}`,
      detail: bulletins.a_valider.join(', '),
      compte: bulletins.a_valider.length,
      lien: '/bulletins',
      urgence: 'normale' as const,
    },
  ].filter((t): t is Tache => !!t)

  return (
    <Panneau titre="À traiter" description={taches.length ? `${taches.length} point(s) demandent votre attention` : undefined}>
      {taches.length === 0 ? (
        <div className="flex flex-col items-center gap-2 py-8 text-center">
          <CircleCheck className="size-6 text-[var(--viz-bon)]" />
          <p className="text-sm font-medium">Tout est à jour</p>
        </div>
      ) : (
        <ul className="-mx-2 space-y-0.5">
          {taches.map(({ icone: Icone, titre, detail, compte, lien, urgence }) => (
            <li key={titre}>
              <Link to={lien} className="group flex items-center gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-muted/60">
                <span
                  className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-lg border',
                    urgence === 'haute'
                      ? 'border-red-200 bg-red-50 text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300'
                      : 'bg-muted/50 text-muted-foreground',
                  )}
                >
                  <Icone className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-medium">{titre}</span>
                  <span className="block truncate text-xs text-muted-foreground">{detail}</span>
                </span>
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-semibold tabular-nums">{compte}</span>
                <ChevronRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
              </Link>
            </li>
          ))}
        </ul>
      )}
      {bulletins.periode && (
        <p className="mt-4 border-t pt-3 text-xs text-muted-foreground">
          Bulletins du {bulletins.periode.libelle} : {bulletins.valides} classe(s) validée(s) sur{' '}
          {bulletins.valides + bulletins.a_valider.length + bulletins.a_calculer.length}
        </p>
      )}
    </Panneau>
  )
}
