import { Loader2, Lock, Save } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useBlocker, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PageHeader } from '@/components/PageHeader'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { affectations } from '@/features/personnes/api'
import { useAnneeActive } from '@/features/structure/api'
import { ErreurChargement } from '@/pages/ErrorPages'
import { getErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAppreciations, useEnregistrerAppreciations } from '../api'
import { Moyenne } from '../components/Moyenne'
import { formatRang } from '../format'
import { MAX_APPRECIATION_MATIERE } from '../schemas'
import type { Appreciations } from '../types'

/** Professeur : appréciations de sa matière sur les bulletins calculés d'une classe. */
export function AppreciationsPage() {
  const [params, setParams] = useSearchParams()
  const anneeActive = useAnneeActive()
  const mesAffectations = affectations.useList({ per_page: 100 })

  const periodes = anneeActive.data?.periodes ?? []
  const periodeParDefaut = [...periodes].reverse().find((p) => p.cloturee) ?? periodes[0]
  const affectationId = Number(params.get('affectation')) || mesAffectations.data?.data[0]?.id
  const periodeId = Number(params.get('periode')) || periodeParDefaut?.id
  const requete = useAppreciations(affectationId, periodeId)

  const choisir = (cle: 'affectation' | 'periode', valeur: string) => {
    const suivants = new URLSearchParams(params)
    suivants.set(cle, valeur)
    setParams(suivants, { replace: true })
  }

  return (
    <>
      <PageHeader title="Appréciations" description="Rédigez l’appréciation de votre matière pour chaque élève, avant la validation des bulletins." />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="space-y-1.5">
          <Label htmlFor="choix-affectation">Classe et matière</Label>
          <Select value={affectationId ? String(affectationId) : ''} onValueChange={(v) => choisir('affectation', v)}>
            <SelectTrigger id="choix-affectation" className="w-full sm:w-72">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {mesAffectations.data?.data.map((a) => (
                <SelectItem key={a.id} value={String(a.id)}>
                  {a.classe?.nom} — {a.matiere?.nom}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="choix-periode">Période</Label>
          <Select value={periodeId ? String(periodeId) : ''} onValueChange={(v) => choisir('periode', v)}>
            <SelectTrigger id="choix-periode" className="w-full sm:w-48">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {periodes.map((p) => (
                <SelectItem key={p.id} value={String(p.id)}>
                  {p.libelle}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {mesAffectations.data?.data.length === 0 && (
        <p className="text-sm text-muted-foreground">Vous n’avez aucune affectation cette année.</p>
      )}
      {requete.isError && <ErreurChargement error={requete.error} />}
      {requete.isPending && !!affectationId && <Skeleton className="h-80 w-full" />}
      {requete.data && <Saisie key={`${affectationId}-${periodeId}-${requete.dataUpdatedAt}`} donnees={requete.data} affectationId={affectationId!} />}
    </>
  )
}

function Saisie({ donnees, affectationId }: { donnees: Appreciations; affectationId: number }) {
  const enregistrement = useEnregistrerAppreciations(affectationId, donnees.periode.id)
  const [textes, setTextes] = useState<Record<number, string>>(() =>
    Object.fromEntries(donnees.lignes.map((l) => [l.id, l.appreciation ?? ''])),
  )

  const modifiees = useMemo(
    () => donnees.lignes.filter((l) => (textes[l.id] ?? '').trim() !== (l.appreciation ?? '')),
    [donnees.lignes, textes],
  )
  const tropLongues = donnees.lignes.filter((l) => (textes[l.id] ?? '').trim().length > MAX_APPRECIATION_MATIERE).length
  const aEnregistrer = modifiees.length > 0

  // Garde-fou : changer de classe / période ou quitter la page avec des appréciations non enregistrées
  const blocker = useBlocker(
    ({ currentLocation, nextLocation }) =>
      aEnregistrer && (currentLocation.pathname !== nextLocation.pathname || currentLocation.search !== nextLocation.search),
  )
  useEffect(() => {
    if (!aEnregistrer) return
    const avertir = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', avertir)
    return () => window.removeEventListener('beforeunload', avertir)
  }, [aEnregistrer])

  const enregistrer = async () => {
    try {
      await enregistrement.mutateAsync(modifiees.map((l) => ({ ligne_id: l.id, appreciation: textes[l.id].trim() || null })))
      toast.success(`${modifiees.length} appréciation(s) enregistrée(s).`)
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  if (!donnees.calcule) {
    return (
      <Alert>
        <Lock />
        <AlertTitle>Bulletins pas encore calculés</AlertTitle>
        <AlertDescription>
          La direction n’a pas encore calculé les bulletins de {donnees.classe.nom} pour {donnees.periode.libelle}. Vous pourrez saisir vos
          appréciations dès qu’ils le seront.
        </AlertDescription>
      </Alert>
    )
  }

  return (
    <>
      {donnees.verrouille && (
        <Alert className="mb-4">
          <Lock />
          <AlertTitle>Bulletins validés</AlertTitle>
          <AlertDescription>Les appréciations de {donnees.classe.nom} sont en lecture seule.</AlertDescription>
        </Alert>
      )}

      <div className="rounded-lg border bg-background">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-48">Élève</TableHead>
              <TableHead className="w-20 text-right">Moyenne</TableHead>
              <TableHead className="hidden w-20 text-right sm:table-cell">Rang</TableHead>
              <TableHead>Appréciation — {donnees.matiere.nom}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {donnees.lignes.map((ligne) => {
              const texte = textes[ligne.id] ?? ''
              const longueur = texte.trim().length
              return (
                <TableRow key={ligne.id}>
                  <TableCell className="align-top font-medium whitespace-normal">
                    {ligne.eleve.nom} {ligne.eleve.prenom}
                  </TableCell>
                  <TableCell className="text-right align-top">
                    {ligne.moyenne === null ? <span className="text-xs text-muted-foreground">Non noté</span> : <Moyenne valeur={ligne.moyenne} />}
                  </TableCell>
                  <TableCell className="hidden text-right align-top tabular-nums sm:table-cell">
                    {ligne.rang === null ? '—' : formatRang(ligne.rang)}
                  </TableCell>
                  <TableCell className="whitespace-normal">
                    <Textarea
                      aria-label={`Appréciation de ${ligne.eleve.prenom} ${ligne.eleve.nom}`}
                      value={texte}
                      readOnly={donnees.verrouille}
                      rows={1}
                      className={cn('min-h-9 resize-none', donnees.verrouille && 'bg-muted')}
                      aria-invalid={longueur > MAX_APPRECIATION_MATIERE}
                      onChange={(e) => setTextes((t) => ({ ...t, [ligne.id]: e.target.value }))}
                    />
                    {longueur > MAX_APPRECIATION_MATIERE * 0.8 && (
                      <p className={cn('mt-1 text-xs', longueur > MAX_APPRECIATION_MATIERE ? 'text-destructive' : 'text-muted-foreground')}>
                        {longueur} / {MAX_APPRECIATION_MATIERE} caractères
                      </p>
                    )}
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      {!donnees.verrouille && (
        <div className="sticky bottom-0 mt-4 flex items-center justify-end gap-3 rounded-lg border bg-background p-3 shadow-sm">
          {tropLongues > 0 && <p className="text-sm text-destructive">{tropLongues} appréciation(s) trop longue(s)</p>}
          <Button onClick={enregistrer} disabled={!aEnregistrer || tropLongues > 0 || enregistrement.isPending}>
            {enregistrement.isPending ? <Loader2 className="animate-spin" /> : <Save />}
            Enregistrer{aEnregistrer && ` (${modifiees.length})`}
          </Button>
        </div>
      )}

      <ConfirmDialog
        open={blocker.state === 'blocked'}
        onOpenChange={(ouvert) => !ouvert && blocker.state === 'blocked' && blocker.reset()}
        title="Quitter sans enregistrer ?"
        description={`${modifiees.length} appréciation(s) modifiée(s) seront perdues.`}
        confirmLabel="Quitter"
        destructive
        onConfirm={async () => blocker.proceed?.()}
      />
    </>
  )
}
