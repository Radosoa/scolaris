import { CalendarX2, GraduationCap, TrendingUp, Wallet } from 'lucide-react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ReferenceLine,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { formatEuros } from '@/features/finances/format'
import { formatNombre } from '@/lib/format'
import { formatPourcentage, libelleMois, libelleSemaine, remplissage } from '../format'
import type { TableauDirection as Donnees } from '../types'
import { Indicateur, Panneau } from './Elements'
import { COULEURS, INFOBULLE } from './graphiques'

const AXE = { fontSize: 12, fill: 'var(--muted-foreground)' }

/** Direction : effectifs, résultats, absentéisme et paiements de l'année. */
export function TableauDirection({ donnees }: { donnees: Donnees }) {
  const { compteurs, effectifs, resultats, absenteisme, finances } = donnees
  const garcons = effectifs.reduce((t, e) => t + e.garcons, 0)
  const filles = effectifs.reduce((t, e) => t + e.filles, 0)
  const capacite = effectifs.reduce((t, e) => t + e.capacite, 0)
  const nonExigible = Math.max(0, finances.reste - finances.retard)

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <Indicateur
          icone={GraduationCap}
          libelle="Élèves inscrits"
          valeur={String(compteurs.eleves)}
          detail={`${compteurs.classes} classes · ${compteurs.enseignants} enseignants · ${remplissage(compteurs.eleves, capacite)} % de remplissage`}
          lien="/eleves"
        />
        <Indicateur
          icone={TrendingUp}
          libelle={`Réussite${resultats.periode ? ` · ${resultats.periode.libelle}` : ''}`}
          valeur={formatPourcentage(resultats.taux_reussite)}
          detail={resultats.moyenne === null ? 'Aucun bulletin calculé' : `Moyenne générale ${formatNombre(resultats.moyenne)} / 20`}
          lien="/bulletins"
        />
        <Indicateur
          icone={CalendarX2}
          libelle="Absentéisme"
          valeur={formatPourcentage(absenteisme.taux, 2)}
          detail={`${formatNombre(absenteisme.heures_non_justifiees)} h non justifiées · ${absenteisme.a_justifier} à justifier`}
          alerte={(absenteisme.taux ?? 0) > 5}
          lien="/absences"
        />
        <Indicateur
          icone={Wallet}
          libelle="Paiements en retard"
          valeur={formatEuros(finances.retard)}
          detail={`${finances.en_retard} famille(s) · ${finances.attendu ? Math.round((finances.encaisse / finances.attendu) * 100) : 0} % encaissé`}
          alerte={finances.retard > 0}
          lien="/paiements"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Panneau titre="Effectifs par classe" description="Garçons et filles, sur la capacité de chaque classe" className="lg:col-span-2">
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={effectifs.map((e) => ({ classe: e.classe.nom, Garçons: e.garcons, Filles: e.filles, capacite: e.capacite }))}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
              <XAxis dataKey="classe" tick={AXE} tickLine={false} axisLine={false} />
              <YAxis tick={AXE} tickLine={false} axisLine={false} allowDecimals={false} width={32} />
              <Tooltip {...INFOBULLE} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="Garçons" stackId="effectif" fill={COULEURS.principale} radius={[0, 0, 0, 0]} />
              <Bar dataKey="Filles" stackId="effectif" fill={COULEURS.violet} radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </Panneau>

        <Panneau titre="Répartition par sexe" description={`${compteurs.eleves} élèves`}>
          <ResponsiveContainer width="100%" height={260}>
            <PieChart>
              <Pie
                data={[
                  { nom: 'Garçons', valeur: garcons },
                  { nom: 'Filles', valeur: filles },
                ]}
                dataKey="valeur"
                nameKey="nom"
                innerRadius={55}
                outerRadius={85}
                paddingAngle={2}
                label={({ name, percent }) => `${name} ${Math.round((percent ?? 0) * 100)} %`}
                labelLine={false}
              >
                <Cell fill={COULEURS.principale} />
                <Cell fill={COULEURS.violet} />
              </Pie>
              <Tooltip {...INFOBULLE} />
            </PieChart>
          </ResponsiveContainer>
        </Panneau>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panneau
          titre="Moyennes par classe"
          description={resultats.periode ? `Bulletins du ${resultats.periode.libelle} · seuil de réussite à 10` : 'Aucun bulletin calculé pour l’instant'}
        >
          {resultats.classes.length === 0 ? (
            <p className="text-sm text-muted-foreground">Les moyennes apparaîtront après le calcul des bulletins.</p>
          ) : (
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={resultats.classes.map((r) => ({ classe: r.classe.nom, Moyenne: r.moyenne, Réussite: r.taux_reussite }))}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
                <XAxis dataKey="classe" tick={AXE} tickLine={false} axisLine={false} />
                <YAxis domain={[0, 20]} ticks={[0, 5, 10, 15, 20]} tick={AXE} tickLine={false} axisLine={false} width={32} />
                <Tooltip {...INFOBULLE} formatter={(valeur, nom) => (nom === 'Moyenne' ? `${formatNombre(Number(valeur))} / 20` : formatPourcentage(Number(valeur)))} />
                <ReferenceLine y={10} stroke={COULEURS.rouge} strokeDasharray="4 4" />
                <Bar dataKey="Moyenne" radius={[4, 4, 0, 0]}>
                  {resultats.classes.map((r) => (
                    <Cell key={r.classe.id} fill={r.moyenne >= 12 ? COULEURS.vert : r.moyenne >= 10 ? COULEURS.principale : COULEURS.ambre} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          )}
        </Panneau>

        <Panneau titre="Absences par semaine" description={`${formatNombre(absenteisme.heures_justifiees + absenteisme.heures_non_justifiees)} h d’absence · ${absenteisme.retards} retards depuis la rentrée`}>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={absenteisme.par_semaine.map((s) => ({ semaine: libelleSemaine(s.semaine), Justifiées: s.justifiees, 'Non justifiées': s.non_justifiees }))}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
              <XAxis dataKey="semaine" tick={AXE} tickLine={false} axisLine={false} />
              <YAxis tick={AXE} tickLine={false} axisLine={false} width={48} unit=" h" />
              <Tooltip {...INFOBULLE} formatter={(valeur) => `${formatNombre(Number(valeur))} h`} labelFormatter={(l) => `Semaine du ${l}`} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="Justifiées" stackId="h" fill={COULEURS.vert} />
              <Bar dataKey="Non justifiées" stackId="h" fill={COULEURS.rouge} radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </Panneau>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Panneau titre="Encaissements par mois" description={`${formatEuros(finances.encaisse)} encaissés sur ${formatEuros(finances.attendu)} attendus`} className="lg:col-span-2">
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={finances.par_mois.map((m) => ({ mois: libelleMois(m.mois), Encaissé: m.montant }))}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--border)" />
              <XAxis dataKey="mois" tick={AXE} tickLine={false} axisLine={false} />
              <YAxis tick={AXE} tickLine={false} axisLine={false} width={56} tickFormatter={(v) => `${Math.round(Number(v) / 1000)} k€`} />
              <Tooltip {...INFOBULLE} formatter={(valeur) => formatEuros(Number(valeur))} />
              <Bar dataKey="Encaissé" fill={COULEURS.vert} radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </Panneau>

        <Panneau titre="État des paiements" description={`Exigible à ce jour : ${formatPourcentage(finances.pourcentage_exigible, 0)} des frais`}>
          <ResponsiveContainer width="100%" height={240}>
            <PieChart>
              <Pie
                data={[
                  { nom: 'Encaissé', valeur: finances.encaisse },
                  { nom: 'À venir', valeur: nonExigible },
                  { nom: 'En retard', valeur: finances.retard },
                ]}
                dataKey="valeur"
                nameKey="nom"
                innerRadius={50}
                outerRadius={80}
                paddingAngle={2}
              >
                <Cell fill={COULEURS.vert} />
                <Cell fill={COULEURS.principale} />
                <Cell fill={COULEURS.rouge} />
              </Pie>
              <Tooltip {...INFOBULLE} formatter={(valeur) => formatEuros(Number(valeur))} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
            </PieChart>
          </ResponsiveContainer>
        </Panneau>
      </div>
    </div>
  )
}
