import { CalendarX2, FileText, Wallet } from 'lucide-react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { formatRang } from '@/features/bulletins/format'
import { formatMontant } from '@/features/finances/format'
import { formatDate, formatNombre } from '@/lib/format'
import type { SuiviEleve } from '../types'
import { Indicateur, Panneau } from './Elements'

/** Résumé d'un élève : bulletin, absences, (finances), notes récentes et (cours du jour). */
export function SuiviEleveCartes({ suivi }: { suivi: SuiviEleve }) {
  const b = suivi.dernier_bulletin

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <Indicateur
          icone={FileText} teinte="emeraude"
          libelle={b ? `Bulletin du ${b.periode}` : 'Bulletin'}
          valeur={b?.moyenne === null || !b ? '—' : `${formatNombre(b.moyenne)} / 20`}
          detail={b ? `${formatRang(b.rang)} sur ${b.effectif}${b.mention ? ` · ${b.mention}` : ''}` : 'Aucun bulletin publié'}
          lien={b ? `/bulletins/${b.id}` : undefined}
        />
        <Indicateur
          icone={CalendarX2} teinte="ambre"
          libelle="Absences"
          valeur={`${formatNombre(suivi.absences.heures_justifiees + suivi.absences.heures_non_justifiees)} h`}
          detail={`${formatNombre(suivi.absences.heures_non_justifiees)} h non justifiées · ${suivi.absences.retards} retard(s)`}
          alerte={suivi.absences.heures_non_justifiees > 0}
        />
        {suivi.finances && (
          <Indicateur
            icone={Wallet} teinte="ciel"
            libelle="Frais de scolarité"
            valeur={suivi.finances.retard > 0 ? formatMontant(suivi.finances.retard) : formatMontant(suivi.finances.reste)}
            detail={suivi.finances.retard > 0 ? 'en retard de paiement' : `restent à payer sur ${formatMontant(suivi.finances.total)}`}
            alerte={suivi.finances.retard > 0}
            lien={`/paiements/eleves/${suivi.id}`}
          />
        )}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panneau titre="Dernières notes" className={suivi.cours_du_jour ? undefined : 'lg:col-span-2'}>
          {suivi.dernieres_notes.length === 0 ? (
            <p className="text-sm text-muted-foreground">Pas encore de note.</p>
          ) : (
            <ul className="divide-y">
              {suivi.dernieres_notes.map((n) => (
                <li key={n.id} className="flex items-center gap-3 py-2 text-sm">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium">{n.matiere}</p>
                    <p className="truncate text-xs text-muted-foreground">
                      {n.titre} · {formatDate(n.date)}
                    </p>
                  </div>
                  {n.absent ? (
                    <Badge variant="outline">Absent</Badge>
                  ) : (
                    <span>
                      <span className="font-medium tabular-nums">{formatNombre(n.valeur)}</span>
                      <span className="text-muted-foreground"> / {formatNombre(n.bareme)}</span>
                    </span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </Panneau>

        {suivi.cours_du_jour && (
          <Panneau
            titre="Aujourd’hui"
            action={
              <Link to="/emploi-du-temps" className="text-sm text-primary hover:underline">
                Emploi du temps
              </Link>
            }
          >
            {suivi.cours_du_jour.length === 0 ? (
              <p className="text-sm text-muted-foreground">Pas de cours aujourd’hui.</p>
            ) : (
              <ul className="space-y-2">
                {suivi.cours_du_jour.map((c) => (
                  <li key={`${c.heure_debut}-${c.matiere}`} className="flex gap-3 rounded-lg border p-2.5 text-sm">
                    <span className="font-medium tabular-nums">
                      {c.heure_debut} – {c.heure_fin}
                    </span>
                    <span className="min-w-0 flex-1">
                      {c.matiere}
                      <span className="block text-xs text-muted-foreground">
                        {c.enseignant}
                        {c.salle && ` · ${c.salle}`}
                      </span>
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Panneau>
        )}
      </div>
    </div>
  )
}
