import { CalendarX2, GraduationCap, Receipt, Wallet } from 'lucide-react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { formatEuros } from '@/features/finances/format'
import { formatDate } from '@/lib/format'
import type { TableauSecretariat as Donnees } from '../types'
import { AnnoncesRecentes, Indicateur, Panneau } from './Elements'

/** Secrétariat : absences à traiter, impayés et derniers encaissements. */
export function TableauSecretariat({ donnees }: { donnees: Donnees }) {
  const { absences, finances, encaissements_7_jours: semaine } = donnees

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <Indicateur icone={GraduationCap} libelle="Élèves inscrits" valeur={String(donnees.eleves)} detail={`Année ${donnees.annee}`} lien="/inscriptions" />
        <Indicateur
          icone={CalendarX2}
          libelle="Absences à justifier"
          valeur={String(absences.a_justifier)}
          detail={`${absences.aujourdhui} élève(s) absent(s) aujourd’hui`}
          alerte={absences.a_justifier > 0}
          lien="/absences"
        />
        <Indicateur
          icone={Wallet}
          libelle="Familles en retard"
          valeur={String(finances.en_retard)}
          detail={`${formatEuros(finances.retard)} exigibles non réglés`}
          alerte={finances.en_retard > 0}
          lien="/paiements"
        />
        <Indicateur icone={Receipt} libelle="Encaissé sur 7 jours" valeur={formatEuros(semaine.montant)} detail={`${semaine.nombre} paiement(s)`} lien="/paiements?onglet=journal" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panneau
          titre="Derniers paiements"
          action={
            <Link to="/paiements?onglet=journal" className="text-sm text-primary hover:underline">
              Journal
            </Link>
          }
        >
          <ul className="divide-y">
            {donnees.derniers_paiements.map((p) => (
              <li key={p.id} className="flex items-center gap-3 py-2 text-sm">
                <div className="min-w-0 flex-1">
                  <Link to={`/paiements/eleves/${p.eleve.id}`} className="font-medium hover:underline">
                    {p.eleve.nom}
                  </Link>
                  <p className="text-xs text-muted-foreground">
                    {p.classe} · {formatDate(p.date)} · <span className="font-mono">{p.numero_recu}</span>
                  </p>
                </div>
                {p.annule ? <Badge variant="destructive">Annulé</Badge> : <span className="font-medium tabular-nums">{formatEuros(p.montant)}</span>}
              </li>
            ))}
          </ul>
        </Panneau>
        <AnnoncesRecentes annonces={donnees.annonces} />
      </div>
    </div>
  )
}
