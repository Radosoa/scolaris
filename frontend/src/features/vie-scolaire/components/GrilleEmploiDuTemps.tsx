import { Plus } from 'lucide-react'
import { cn } from '@/lib/utils'
import { plageDe, position, teinteMatiere } from '../emploi-du-temps'
import type { Creneau, Jour } from '../types'

const JOURS: Record<Jour, string> = { 1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi', 5: 'Vendredi', 6: 'Samedi' }

/** Palette des matières (fond clair, bordure et texte contrastés, variantes sombres). */
const TEINTES = [
  'bg-sky-50 border-sky-300 text-sky-950 dark:bg-sky-950/40 dark:border-sky-800 dark:text-sky-100',
  'bg-emerald-50 border-emerald-300 text-emerald-950 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-100',
  'bg-amber-50 border-amber-300 text-amber-950 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-100',
  'bg-rose-50 border-rose-300 text-rose-950 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-100',
  'bg-violet-50 border-violet-300 text-violet-950 dark:bg-violet-950/40 dark:border-violet-800 dark:text-violet-100',
  'bg-teal-50 border-teal-300 text-teal-950 dark:bg-teal-950/40 dark:border-teal-800 dark:text-teal-100',
  'bg-orange-50 border-orange-300 text-orange-950 dark:bg-orange-950/40 dark:border-orange-800 dark:text-orange-100',
  'bg-indigo-50 border-indigo-300 text-indigo-950 dark:bg-indigo-950/40 dark:border-indigo-800 dark:text-indigo-100',
  'bg-lime-50 border-lime-300 text-lime-950 dark:bg-lime-950/40 dark:border-lime-800 dark:text-lime-100',
  'bg-fuchsia-50 border-fuchsia-300 text-fuchsia-950 dark:bg-fuchsia-950/40 dark:border-fuchsia-800 dark:text-fuchsia-100',
]

const HAUTEUR_HEURE = 'h-14'

interface Props {
  creneaux: Creneau[]
  /** Information secondaire d'un créneau : la classe (vue professeur) ou le professeur (vue classe) */
  afficher: 'classe' | 'enseignant'
  onCreneau?: (creneau: Creneau) => void
  /** Si fourni, chaque heure libre propose d'ajouter un créneau (direction) */
  onAjouter?: (jour: Jour, heure: number) => void
}

/** Semaine type : une colonne par jour, des blocs positionnés selon l'horaire. */
export function GrilleEmploiDuTemps({ creneaux, afficher, onCreneau, onAjouter }: Props) {
  const plage = plageDe(creneaux)
  const heures = Array.from({ length: plage.fin - plage.debut }, (_, i) => plage.debut + i)

  return (
    <div className="overflow-x-auto rounded-lg border bg-background">
      <div
        className="grid min-w-[44rem]"
        style={{ gridTemplateColumns: `3.5rem repeat(${plage.jours.length}, minmax(0, 1fr))` }}
      >
        <div className="border-b" />
        {plage.jours.map((jour) => (
          <div key={jour} className="border-b border-l px-2 py-2 text-center text-sm font-medium">
            {JOURS[jour]}
          </div>
        ))}

        <div>
          {heures.map((heure) => (
            <div key={heure} className={cn(HAUTEUR_HEURE, 'border-b pr-2 pt-1 text-right text-xs text-muted-foreground tabular-nums')}>
              {String(heure).padStart(2, '0')}:00
            </div>
          ))}
        </div>

        {plage.jours.map((jour) => (
          <div key={jour} className="relative border-l" data-jour={jour}>
            {heures.map((heure) =>
              onAjouter ? (
                <button
                  key={heure}
                  type="button"
                  onClick={() => onAjouter(jour, heure)}
                  aria-label={`Ajouter un cours le ${JOURS[jour].toLowerCase()} à ${heure} h`}
                  className={cn(HAUTEUR_HEURE, 'group flex w-full items-center justify-center border-b hover:bg-accent/50')}
                >
                  <Plus className="size-4 text-muted-foreground opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100" />
                </button>
              ) : (
                <div key={heure} className={cn(HAUTEUR_HEURE, 'border-b')} />
              ),
            )}

            {creneaux
              .filter((c) => c.jour === jour)
              .map((c) => {
                const { top, height } = position(c, plage)
                const secondaire = afficher === 'classe' ? c.classe?.nom : c.enseignant?.nom_complet
                return (
                  <button
                    key={c.id}
                    type="button"
                    onClick={() => onCreneau?.(c)}
                    disabled={!onCreneau}
                    data-creneau={c.id}
                    title={`${c.matiere?.nom} · ${c.heure_debut} – ${c.heure_fin}${c.salle ? ` · ${c.salle}` : ''} · ${secondaire ?? ''}`}
                    className={cn(
                      'absolute inset-x-1 overflow-hidden rounded-md border-l-4 border px-2 py-1 text-left text-xs shadow-xs transition-shadow',
                      onCreneau && 'hover:shadow-md focus-visible:outline-2 focus-visible:outline-ring',
                      // Vue professeur : une couleur par classe ; vue classe : une couleur par matière
                      TEINTES[teinteMatiere((afficher === 'classe' ? c.classe?.nom : c.matiere?.code) ?? '', TEINTES.length)],
                    )}
                    style={{ top: `calc(${top}% + 2px)`, height: `calc(${height}% - 4px)` }}
                  >
                    <p className="truncate font-semibold">{c.matiere?.nom}</p>
                    <p className="truncate opacity-80">
                      {c.heure_debut} – {c.heure_fin}
                    </p>
                    <p className="truncate opacity-80">
                      {secondaire}
                      {c.salle && ` · ${c.salle}`}
                    </p>
                  </button>
                )
              })}
          </div>
        ))}
      </div>
    </div>
  )
}
