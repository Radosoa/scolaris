import { CheckCircle2, Circle, TriangleAlert } from 'lucide-react'
import { Link } from 'react-router'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Moyenne } from '@/features/bulletins/components/Moyenne'
import { aujourdhuiIso, formatDate } from '@/lib/format'
import type { TableauProfesseur as Donnees } from '../types'
import { AnnoncesRecentes, Panneau } from './Elements'

/** Professeur : cours du jour avec l'appel, appels oubliés, résultats de ses classes. */
export function TableauProfesseur({ donnees }: { donnees: Donnees }) {
  const aujourdhui = aujourdhuiIso()

  return (
    <div className="space-y-4">
      {donnees.appels_en_attente.length > 0 && (
        <Alert>
          <TriangleAlert />
          <AlertTitle>{donnees.appels_en_attente.length} appel(s) non fait(s) ces deux dernières semaines</AlertTitle>
          <AlertDescription>
            <ul className="flex flex-wrap gap-x-4 gap-y-1">
              {donnees.appels_en_attente.slice(0, 6).map((a) => (
                <li key={`${a.creneau_id}-${a.date}`}>
                  <Link to={`/appel?date=${a.date}&creneau=${a.creneau_id}`} className="underline">
                    {formatDate(a.date)} {a.heure_debut} · {a.classe}
                  </Link>
                </li>
              ))}
            </ul>
          </AlertDescription>
        </Alert>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <Panneau titre="Cours du jour" description={donnees.cours_du_jour.length ? 'Cliquez sur un cours pour faire l’appel' : undefined}>
          {donnees.cours_du_jour.length === 0 ? (
            <p className="text-sm text-muted-foreground">Pas de cours aujourd’hui.</p>
          ) : (
            <ul className="space-y-2">
              {donnees.cours_du_jour.map((c) => (
                <li key={c.creneau_id}>
                  <Link
                    to={`/appel?date=${aujourdhui}&creneau=${c.creneau_id}`}
                    className="flex items-center gap-3 rounded-lg border p-2.5 text-sm transition-colors hover:bg-accent/50"
                  >
                    {c.appel_fait ? <CheckCircle2 className="size-4 text-emerald-600" aria-label="Appel fait" /> : <Circle className="size-4 text-muted-foreground" aria-label="Appel à faire" />}
                    <span className="font-medium tabular-nums">
                      {c.heure_debut} – {c.heure_fin}
                    </span>
                    <span>{c.classe}</span>
                    <span className="ml-auto text-xs text-muted-foreground">{c.salle}</span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Panneau>

        <Panneau titre="Mes classes" description="Moyenne de la classe dans votre matière au dernier bulletin calculé">
          <ul className="divide-y">
            {donnees.classes.map((c) => (
              <li key={c.affectation_id} className="flex items-center gap-3 py-2 text-sm">
                <div className="min-w-0 flex-1">
                  <Link to={`/notes?affectation=${c.affectation_id}`} className="font-medium hover:underline">
                    {c.classe}
                  </Link>
                  <p className="text-xs text-muted-foreground">
                    {c.matiere} · {c.effectif} élèves
                  </p>
                </div>
                {c.moyenne === null ? (
                  <span className="text-xs text-muted-foreground">Pas encore de bulletin</span>
                ) : (
                  <span>
                    <Moyenne valeur={c.moyenne} /> <span className="text-xs text-muted-foreground">/ 20 · {c.periode}</span>
                  </span>
                )}
              </li>
            ))}
          </ul>
        </Panneau>
      </div>

      <AnnoncesRecentes annonces={donnees.annonces} />
    </div>
  )
}
