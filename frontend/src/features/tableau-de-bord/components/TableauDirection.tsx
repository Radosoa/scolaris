import { CalendarX2, GraduationCap, TrendingUp, Wallet } from 'lucide-react'
import { Bar, BarChart, CartesianGrid, LabelList, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatMontant, formatMontantCompact } from '@/features/finances/format'
import { formatNombre } from '@/lib/format'
import { formatPourcentage, libelleMois, libelleSemaine, remplissage } from '../format'
import { libelleVariation, tonVariation, variation } from '../tendances'
import type { TableauDirection as Donnees } from '../types'
import { ATraiter } from './ATraiter'
import { Indicateur, LienPanneau, Panneau } from './Elements'
import { AXE, COULEURS } from './graphiques'
import { Infobulle, Jauge, Legende, Sparkline } from './Visuels'

const GRILLE = { vertical: false, stroke: COULEURS.grille } as const
const RAYON_HAUT: [number, number, number, number] = [4, 4, 0, 0]

/** Direction : indicateurs clés, actions en attente, effectifs, résultats, absentéisme, recouvrement. */
export function TableauDirection({ donnees }: { donnees: Donnees }) {
  const { compteurs, effectifs, resultats, absenteisme, finances } = donnees

  const capacite = effectifs.reduce((t, e) => t + e.capacite, 0)
  const garcons = effectifs.reduce((t, e) => t + e.garcons, 0)
  const filles = effectifs.reduce((t, e) => t + e.filles, 0)

  // Absences : total hebdomadaire et évolution sur la dernière semaine
  const semaines = absenteisme.par_semaine.map((s) => s.justifiees + s.non_justifiees)
  const evolution = semaines.length >= 2 ? variation(semaines.at(-1)!, semaines.at(-2)!) : null

  const partEncaissee = finances.attendu ? Math.round((finances.encaisse / finances.attendu) * 100) : 0
  const aVenir = Math.max(0, finances.reste - finances.retard)

  return (
    <div className="space-y-4">
      {/* Indicateurs clés */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Indicateur
          icone={GraduationCap}
          libelle="Élèves inscrits"
          valeur={String(compteurs.eleves)}
          detail={`${compteurs.classes} classes · ${compteurs.enseignants} enseignants`}
          lien="/eleves"
        >
          <div className="space-y-1.5">
            <Jauge pourcentage={remplissage(compteurs.eleves, capacite)} couleur={COULEURS.serie1} libelle="Taux de remplissage" />
            <p className="text-xs text-muted-foreground">{remplissage(compteurs.eleves, capacite)} % de la capacité d’accueil</p>
          </div>
        </Indicateur>

        <Indicateur
          icone={TrendingUp}
          libelle="Taux de réussite"
          valeur={formatPourcentage(resultats.taux_reussite)}
          detail={resultats.moyenne === null ? 'Aucun bulletin calculé' : `Moyenne générale ${formatNombre(resultats.moyenne)} / 20`}
          lien="/bulletins"
        >
          <div className="space-y-1.5">
            <Jauge pourcentage={resultats.taux_reussite ?? 0} couleur={COULEURS.bon} libelle="Élèves à 10 ou plus" />
            <p className="text-xs text-muted-foreground">Élèves à 10 / 20 ou plus{resultats.periode ? ` · ${resultats.periode.libelle}` : ''}</p>
          </div>
        </Indicateur>

        <Indicateur
          icone={CalendarX2}
          libelle="Absentéisme"
          valeur={formatPourcentage(absenteisme.taux, 2)}
          detail={`${formatNombre(absenteisme.heures_non_justifiees)} h non justifiées`}
          alerte={(absenteisme.taux ?? 0) > 5}
          lien="/absences"
          tendance={
            evolution
              ? { libelle: libelleVariation(evolution), sens: evolution.sens, ton: tonVariation(evolution.sens, false), contexte: 'vs semaine précédente' }
              : undefined
          }
        >
          <Sparkline valeurs={semaines} couleur={COULEURS.serie1} libelle="Heures d’absence par semaine depuis la rentrée" />
        </Indicateur>

        <Indicateur icone={Wallet} libelle="Frais encaissés" valeur={formatMontant(finances.encaisse)} detail={`sur ${formatMontant(finances.attendu)} attendus`} lien="/paiements">
          <div className="space-y-1.5">
            <Jauge pourcentage={partEncaissee} couleur={COULEURS.bon} libelle="Part des frais encaissée" />
            <p className="text-xs text-muted-foreground">{partEncaissee} % de l’année encaissés</p>
          </div>
        </Indicateur>
      </div>

      {/* Actions en attente et effectifs */}
      <div className="grid gap-4 lg:grid-cols-3">
        <ATraiter donnees={donnees} />

        <Panneau
          className="lg:col-span-2"
          titre="Effectifs par classe"
          description={`${compteurs.eleves} élèves pour ${capacite} places`}
          action={<LienPanneau to="/structure/classes">Classes</LienPanneau>}
        >
          <Repartition
            segments={[
              { libelle: 'Garçons', valeur: garcons, couleur: COULEURS.serie1 },
              { libelle: 'Filles', valeur: filles, couleur: COULEURS.serie2 },
            ]}
            formater={(v) => String(v)}
          />
          <div className="mt-4 h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={effectifs.map((e) => ({ classe: e.classe.nom, Garçons: e.garcons, Filles: e.filles }))} margin={{ top: 8, right: 4, left: -16, bottom: 0 }}>
                <CartesianGrid {...GRILLE} />
                <XAxis dataKey="classe" tick={AXE} tickLine={false} axisLine={{ stroke: COULEURS.grille }} />
                <YAxis tick={AXE} tickLine={false} axisLine={false} allowDecimals={false} />
                <Tooltip cursor={{ fill: 'var(--muted)', opacity: 0.5 }} content={<Infobulle formater={(v) => `${v} élève(s)`} />} />
                <Bar dataKey="Garçons" stackId="effectif" fill={COULEURS.serie1} maxBarSize={24} stroke="var(--card)" strokeWidth={2} />
                <Bar dataKey="Filles" stackId="effectif" fill={COULEURS.serie2} maxBarSize={24} stroke="var(--card)" strokeWidth={2} radius={RAYON_HAUT} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panneau>
      </div>

      {/* Résultats et absentéisme */}
      <div className="grid gap-4 lg:grid-cols-2">
        <Panneau
          titre="Moyennes par classe"
          description={resultats.periode ? `Bulletins du ${resultats.periode.libelle} · seuil de réussite à 10 / 20` : 'Aucun bulletin calculé pour l’instant'}
          action={<LienPanneau to="/bulletins">Bulletins</LienPanneau>}
        >
          {resultats.classes.length === 0 ? (
            <p className="py-10 text-center text-sm text-muted-foreground">Les moyennes apparaîtront après le calcul des bulletins.</p>
          ) : (
            <>
              <Legende
                className="mb-3"
                entrees={[
                  { libelle: 'Moyenne de la classe', couleur: COULEURS.serie1 },
                  { libelle: 'Seuil de réussite', couleur: COULEURS.critique, valeur: '10 / 20' },
                ]}
              />
              <div className="h-60">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={resultats.classes.map((r) => ({ classe: r.classe.nom, Moyenne: r.moyenne }))} margin={{ top: 20, right: 4, left: -20, bottom: 0 }}>
                    <CartesianGrid {...GRILLE} />
                    <XAxis dataKey="classe" tick={AXE} tickLine={false} axisLine={{ stroke: COULEURS.grille }} />
                    <YAxis domain={[0, 20]} ticks={[0, 5, 10, 15, 20]} tick={AXE} tickLine={false} axisLine={false} />
                    <Tooltip cursor={{ fill: 'var(--muted)', opacity: 0.5 }} content={<Infobulle formater={(v) => `${formatNombre(v)} / 20`} />} />
                    <ReferenceLine y={10} stroke={COULEURS.critique} strokeOpacity={0.8} />
                    {/* Pas d'animation : les valeurs au-dessus des barres restent toujours affichées */}
                    <Bar dataKey="Moyenne" fill={COULEURS.serie1} maxBarSize={24} radius={RAYON_HAUT} isAnimationActive={false}>
                      <LabelList dataKey="Moyenne" position="top" formatter={(v) => formatNombre(Number(v))} style={{ fontSize: 11, fill: 'var(--muted-foreground)' }} />
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </>
          )}
        </Panneau>

        <Panneau
          titre="Absences par semaine"
          description={`${formatNombre(absenteisme.heures_justifiees + absenteisme.heures_non_justifiees)} h depuis la rentrée · ${absenteisme.retards} retards`}
          action={<LienPanneau to="/absences">Absences</LienPanneau>}
        >
          <Legende
            className="mb-3"
            entrees={[
              { libelle: 'Justifiées', couleur: COULEURS.neutre, valeur: `${formatNombre(absenteisme.heures_justifiees)} h` },
              { libelle: 'Non justifiées', couleur: COULEURS.critique, valeur: `${formatNombre(absenteisme.heures_non_justifiees)} h` },
            ]}
          />
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart
                data={absenteisme.par_semaine.map((s) => ({ semaine: libelleSemaine(s.semaine), Justifiées: s.justifiees, 'Non justifiées': s.non_justifiees }))}
                margin={{ top: 8, right: 4, left: -12, bottom: 0 }}
              >
                <CartesianGrid {...GRILLE} />
                <XAxis dataKey="semaine" tick={AXE} tickLine={false} axisLine={{ stroke: COULEURS.grille }} />
                <YAxis tick={AXE} tickLine={false} axisLine={false} unit=" h" />
                <Tooltip
                  cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                  content={<Infobulle formater={(v) => `${formatNombre(v)} h`} titre={(l) => `Semaine du ${l}`} />}
                />
                <Bar dataKey="Justifiées" stackId="h" fill={COULEURS.neutre} maxBarSize={24} stroke="var(--card)" strokeWidth={2} />
                <Bar dataKey="Non justifiées" stackId="h" fill={COULEURS.critique} maxBarSize={24} stroke="var(--card)" strokeWidth={2} radius={RAYON_HAUT} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panneau>
      </div>

      {/* Recouvrement */}
      <div className="grid gap-4 lg:grid-cols-3">
        <Panneau
          className="lg:col-span-2"
          titre="Encaissements par mois"
          description={`${formatMontant(finances.encaisse)} encaissés depuis la rentrée`}
          action={<LienPanneau to="/paiements?onglet=journal">Journal</LienPanneau>}
        >
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={finances.par_mois.map((m) => ({ mois: libelleMois(m.mois), Encaissé: m.montant }))} margin={{ top: 20, right: 4, left: 0, bottom: 0 }}>
                <CartesianGrid {...GRILLE} />
                <XAxis dataKey="mois" tick={AXE} tickLine={false} axisLine={{ stroke: COULEURS.grille }} />
                <YAxis tick={AXE} tickLine={false} axisLine={false} width={48} tickFormatter={(v) => formatMontantCompact(v)} />
                <Tooltip cursor={{ fill: 'var(--muted)', opacity: 0.5 }} content={<Infobulle formater={(v) => formatMontant(v)} />} />
                <Bar dataKey="Encaissé" fill={COULEURS.serie1} maxBarSize={24} radius={RAYON_HAUT} isAnimationActive={false}>
                  <LabelList dataKey="Encaissé" position="top" formatter={(v) => formatMontantCompact(v)} style={{ fontSize: 11, fill: 'var(--muted-foreground)' }} />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panneau>

        <Panneau
          titre="État du recouvrement"
          description={`Exigible à ce jour : ${formatPourcentage(finances.pourcentage_exigible, 0)} des frais annuels`}
          action={<LienPanneau to="/paiements">Impayés</LienPanneau>}
        >
          <Repartition
            vertical
            segments={[
              { libelle: 'Encaissé', valeur: finances.encaisse, couleur: COULEURS.bon },
              { libelle: 'À venir', valeur: aVenir, couleur: COULEURS.neutre },
              { libelle: 'En retard', valeur: finances.retard, couleur: COULEURS.critique },
            ]}
            formater={(v) => formatMontant(v)}
          />
          <p className="mt-4 border-t pt-3 text-xs text-muted-foreground">
            {finances.en_retard} famille(s) en retard sur {finances.eleves} élèves
          </p>
        </Panneau>
      </div>
    </div>
  )
}

interface Segment {
  libelle: string
  valeur: number
  couleur: string
}

/**
 * Répartition d'un total en barre segmentée (100 %), avec légende chiffrée.
 * Remplace les camemberts : les parts se comparent sur une même ligne.
 */
function Repartition({ segments, formater, vertical = false }: { segments: Segment[]; formater: (v: number) => string; vertical?: boolean }) {
  const total = segments.reduce((t, s) => t + s.valeur, 0)
  const part = (v: number) => (total ? Math.round((v / total) * 100) : 0)

  return (
    <div className="space-y-3">
      <div className="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full" role="img" aria-label={segments.map((s) => `${s.libelle} ${part(s.valeur)} %`).join(', ')}>
        {segments
          .filter((s) => s.valeur > 0)
          .map((s) => (
            <div key={s.libelle} className="h-full first:rounded-l-full last:rounded-r-full" style={{ width: `${(s.valeur / total) * 100}%`, background: s.couleur }} />
          ))}
      </div>
      {vertical ? (
        <ul className="space-y-2.5">
          {segments.map((s) => (
            <li key={s.libelle} className="flex items-center gap-2.5 text-sm">
              <span aria-hidden className="size-2.5 rounded-[3px]" style={{ background: s.couleur }} />
              <span className="text-muted-foreground">{s.libelle}</span>
              <span className="ml-auto font-medium tabular-nums">{formater(s.valeur)}</span>
              <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">{part(s.valeur)} %</span>
            </li>
          ))}
        </ul>
      ) : (
        <Legende entrees={segments.map((s) => ({ libelle: s.libelle, couleur: s.couleur, valeur: formater(s.valeur), detail: `${part(s.valeur)} %` }))} />
      )}
    </div>
  )
}
