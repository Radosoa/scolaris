import { CheckCircle2, Circle, Clock, Loader2, Save, UserCheck, UserX } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useBlocker, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PageHeader } from '@/components/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/utils'
import { ErreurChargement } from '@/pages/ErrorPages'
import { useCoursDuJour, useEnregistrerAppel, useFeuilleAppel } from '../api'
import { aujourdhuiIso, compterAppel } from '../emploi-du-temps'
import type { FeuilleAppel, LigneAppel, StatutAppel } from '../types'

/** Professeur : choisir un de ses cours du jour, puis faire l'appel. */
export function AppelPage() {
  const [params, setParams] = useSearchParams()
  const [aujourdhui] = useState(aujourdhuiIso)
  const date = params.get('date') || aujourdhui
  const cours = useCoursDuJour(date)
  const creneauId = Number(params.get('creneau')) || cours.data?.find((c) => !c.appel_fait)?.creneau.id || cours.data?.[0]?.creneau.id
  const feuille = useFeuilleAppel(creneauId, date)

  const choisir = (suivants: Record<string, string>) => setParams(new URLSearchParams({ date, ...suivants }), { replace: true })

  return (
    <>
      <PageHeader title="Appel" description="Les élèves sont présents par défaut : signalez seulement les absents et les retards." />

      <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
        <aside className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="appel-date">Date</Label>
            <Input
              id="appel-date"
              type="date"
              max={aujourdhui}
              value={date}
              onChange={(e) => e.target.value && setParams(new URLSearchParams({ date: e.target.value }), { replace: true })}
            />
          </div>

          {cours.isPending && <Skeleton className="h-40 w-full" />}
          {cours.data?.length === 0 && <p className="text-sm text-muted-foreground">Aucun cours le {formatDate(date)}.</p>}
          <ul className="space-y-2">
            {cours.data?.map(({ creneau, appel_fait }) => (
              <li key={creneau.id}>
                <button
                  type="button"
                  onClick={() => choisir({ creneau: String(creneau.id) })}
                  aria-current={creneau.id === creneauId}
                  className={cn(
                    'flex w-full items-start gap-3 rounded-lg border bg-background p-3 text-left transition-colors hover:bg-accent/50',
                    creneau.id === creneauId && 'border-primary ring-1 ring-primary',
                  )}
                >
                  {appel_fait ? (
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" aria-label="Appel fait" />
                  ) : (
                    <Circle className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-label="Appel à faire" />
                  )}
                  <span className="min-w-0">
                    <span className="block text-sm font-medium tabular-nums">
                      {creneau.heure_debut} – {creneau.heure_fin} · {creneau.classe?.nom}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                      {creneau.matiere?.nom}
                      {creneau.salle && ` · ${creneau.salle}`}
                    </span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        </aside>

        <section>
          {feuille.isError && <ErreurChargement error={feuille.error} />}
          {feuille.isPending && !!creneauId && <Skeleton className="h-96 w-full" />}
          {feuille.data && <Feuille key={`${creneauId}-${date}-${feuille.dataUpdatedAt}`} feuille={feuille.data} />}
        </section>
      </div>
    </>
  )
}

const STATUTS: { valeur: StatutAppel; libelle: string; icone: typeof UserCheck; actif: string }[] = [
  { valeur: 'present', libelle: 'Présent', icone: UserCheck, actif: 'bg-emerald-600 text-white hover:bg-emerald-600/90' },
  { valeur: 'absent', libelle: 'Absent', icone: UserX, actif: 'bg-red-600 text-white hover:bg-red-600/90' },
  { valeur: 'retard', libelle: 'Retard', icone: Clock, actif: 'bg-amber-500 text-white hover:bg-amber-500/90' },
]

function Feuille({ feuille }: { feuille: FeuilleAppel }) {
  const { creneau, date } = feuille
  const enregistrement = useEnregistrerAppel(creneau.id, date)
  const [lignes, setLignes] = useState<LigneAppel[]>(feuille.eleves)

  const compte = compterAppel(lignes)
  const modifiees = useMemo(
    () => lignes.filter((l, i) => l.statut !== feuille.eleves[i].statut || l.minutes_retard !== feuille.eleves[i].minutes_retard).length,
    [lignes, feuille.eleves],
  )
  const retardsIncomplets = lignes.some((l) => l.statut === 'retard' && !(l.minutes_retard && l.minutes_retard > 0))

  const blocker = useBlocker(
    ({ currentLocation, nextLocation }) =>
      modifiees > 0 && (currentLocation.pathname !== nextLocation.pathname || currentLocation.search !== nextLocation.search),
  )
  useEffect(() => {
    if (modifiees === 0) return
    const avertir = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', avertir)
    return () => window.removeEventListener('beforeunload', avertir)
  }, [modifiees])

  const modifier = (eleveId: number, changement: Partial<LigneAppel>) =>
    setLignes((actuelles) => actuelles.map((l) => (l.eleve_id === eleveId ? { ...l, ...changement } : l)))

  const enregistrer = async () => {
    try {
      // Seuls les absents et retards sont transmis : les autres élèves sont présents
      await enregistrement.mutateAsync(
        lignes
          .filter((l) => l.statut !== 'present')
          .map((l) => ({ eleve_id: l.eleve_id, statut: l.statut, minutes_retard: l.statut === 'retard' ? l.minutes_retard : null })),
      )
      toast.success(`Appel enregistré : ${compte.absent} absent(s), ${compte.retard} retard(s).`)
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {creneau.classe?.nom} · {creneau.matiere?.nom}
        </CardTitle>
        <CardDescription>
          {creneau.jour_libelle} {formatDate(date)}, {creneau.heure_debut} – {creneau.heure_fin}
          {creneau.salle && ` · ${creneau.salle}`}
          {feuille.appel
            ? ` · appel fait le ${new Date(feuille.appel.fait_le).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}${feuille.appel.fait_par ? ` par ${feuille.appel.fait_par}` : ''}`
            : ' · appel non fait'}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <Badge variant="secondary">{compte.present} présent(s)</Badge>
          <Badge variant={compte.absent ? 'destructive' : 'secondary'}>{compte.absent} absent(s)</Badge>
          <Badge variant="secondary">{compte.retard} retard(s)</Badge>
          <Button
            variant="ghost"
            size="sm"
            className="ml-auto"
            onClick={() => setLignes((actuelles) => actuelles.map((l) => ({ ...l, statut: 'present', minutes_retard: null })))}
          >
            Tous présents
          </Button>
        </div>

        <ul className="divide-y rounded-lg border">
          {lignes.map((ligne) => (
            <li key={ligne.eleve_id} className="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center" data-eleve={ligne.eleve_id}>
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">
                  {ligne.nom} {ligne.prenom}
                </p>
                {ligne.justifiee && ligne.statut !== 'present' && <p className="text-xs text-muted-foreground">Justifié par la vie scolaire</p>}
              </div>
              {ligne.statut === 'retard' && (
                <div className="flex items-center gap-1.5">
                  <Input
                    type="number"
                    min={1}
                    max={240}
                    aria-label={`Minutes de retard de ${ligne.prenom} ${ligne.nom}`}
                    value={ligne.minutes_retard ?? ''}
                    onChange={(e) => modifier(ligne.eleve_id, { minutes_retard: e.target.value ? Number(e.target.value) : null })}
                    aria-invalid={!ligne.minutes_retard}
                    className="h-8 w-20 text-right"
                  />
                  <span className="text-xs text-muted-foreground">min</span>
                </div>
              )}
              <div className="inline-flex rounded-lg border p-0.5" role="group" aria-label={`Présence de ${ligne.prenom} ${ligne.nom}`}>
                {STATUTS.map(({ valeur, libelle, icone: Icone, actif }) => (
                  <Button
                    key={valeur}
                    type="button"
                    size="sm"
                    variant="ghost"
                    aria-pressed={ligne.statut === valeur}
                    className={cn('h-7', ligne.statut === valeur && actif)}
                    onClick={() =>
                      modifier(ligne.eleve_id, {
                        statut: valeur,
                        minutes_retard: valeur === 'retard' ? (ligne.minutes_retard ?? 5) : null,
                      })
                    }
                  >
                    <Icone />
                    {libelle}
                  </Button>
                ))}
              </div>
            </li>
          ))}
        </ul>

        <div className="flex items-center justify-end gap-3">
          {retardsIncomplets && <p className="text-sm text-destructive">Indiquez la durée de chaque retard.</p>}
          <Button onClick={enregistrer} disabled={enregistrement.isPending || retardsIncomplets || (!!feuille.appel && modifiees === 0)}>
            {enregistrement.isPending ? <Loader2 className="animate-spin" /> : <Save />}
            {feuille.appel ? 'Enregistrer les modifications' : 'Valider l’appel'}
          </Button>
        </div>
      </CardContent>

      <ConfirmDialog
        open={blocker.state === 'blocked'}
        onOpenChange={(ouvert) => !ouvert && blocker.state === 'blocked' && blocker.reset()}
        title="Quitter sans enregistrer ?"
        description="L’appel en cours n’est pas enregistré."
        confirmLabel="Quitter"
        destructive
        onConfirm={async () => blocker.proceed?.()}
      />
    </Card>
  )
}
