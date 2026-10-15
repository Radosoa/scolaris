import { Loader2, RotateCcw, Save } from 'lucide-react'
import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { SelectField } from '@/components/form/SelectField'
import { getErrorMessage } from '@/lib/api'
import { formatNombre } from '@/lib/format'
import { niveaux, TOUS, useCoefficients, useEnregistrerCoefficients } from '../api'

/** Valeurs saisies, par matière : chaîne vide = matière non enseignée. */
type Saisie = Record<number, string>

function valeurValide(texte: string): boolean {
  if (texte.trim() === '') return true
  const valeur = Number(texte.replace(',', '.'))
  return Number.isFinite(valeur) && valeur >= 0.5 && valeur <= 20
}

export function CoefficientsGrille() {
  const listeNiveaux = niveaux.useList(TOUS)
  const [niveauChoisi, setNiveauChoisi] = useState<number>()
  const niveauId = niveauChoisi ?? listeNiveaux.data?.data[0]?.id

  return (
    <div className="space-y-4">
      <div className="max-w-xs space-y-2">
        <Label htmlFor="niveau">Niveau</Label>
        <SelectField
          id="niveau"
          value={niveauId}
          onChange={setNiveauChoisi}
          options={listeNiveaux.data?.data.map((n) => ({ value: n.id, label: n.libelle })) ?? []}
          placeholder="Choisir un niveau"
        />
      </div>
      {/* key : repart d'une saisie vierge à chaque changement de niveau */}
      {niveauId && <Grille key={niveauId} niveauId={niveauId} />}
    </div>
  )
}

function Grille({ niveauId }: { niveauId: number }) {
  const grille = useCoefficients(niveauId)
  const enregistrement = useEnregistrerCoefficients(niveauId)
  // Seules les valeurs modifiées sont stockées ; les autres viennent du serveur
  const [modifs, setModifs] = useState<Saisie>({})

  const initiale = useMemo<Saisie>(
    () => Object.fromEntries((grille.data ?? []).map((l) => [l.matiere_id, l.valeur === null ? '' : String(l.valeur)])),
    [grille.data],
  )

  if (grille.isPending) return <Skeleton className="h-64 w-full" />
  if (!grille.data?.length) {
    return <p className="text-sm text-muted-foreground">Créez d’abord des matières dans l’onglet « Matières ».</p>
  }

  const saisie: Saisie = { ...initiale, ...modifs }
  const modifiees = grille.data.filter((l) => (saisie[l.matiere_id] ?? '') !== initiale[l.matiere_id])
  const invalides = grille.data.filter((l) => !valeurValide(saisie[l.matiere_id] ?? ''))
  const totalCoefficients = grille.data.reduce((total, l) => {
    const texte = saisie[l.matiere_id] ?? ''
    return texte && valeurValide(texte) ? total + Number(texte.replace(',', '.')) : total
  }, 0)

  const enregistrer = async () => {
    try {
      await enregistrement.mutateAsync(
        modifiees.map((l) => {
          const texte = saisie[l.matiere_id].trim()
          return { matiere_id: l.matiere_id, valeur: texte === '' ? null : Number(texte.replace(',', '.')) }
        }),
      )
      setModifs({})
      toast.success('Coefficients enregistrés.')
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  return (
    <div className="space-y-3">
      <div className="rounded-lg border bg-background">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Matière</TableHead>
              <TableHead className="hidden sm:table-cell">Code</TableHead>
              <TableHead className="w-32 text-right">Coefficient</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {grille.data.map((ligne) => {
              const texte = saisie[ligne.matiere_id] ?? ''
              const invalide = !valeurValide(texte)
              return (
                <TableRow key={ligne.matiere_id} className={texte === '' ? 'text-muted-foreground' : undefined}>
                  <TableCell>
                    <label htmlFor={`coef-${ligne.matiere_id}`}>{ligne.nom}</label>
                  </TableCell>
                  <TableCell className="hidden sm:table-cell">{ligne.code}</TableCell>
                  <TableCell className="text-right">
                    <Input
                      id={`coef-${ligne.matiere_id}`}
                      inputMode="decimal"
                      placeholder="—"
                      value={texte}
                      aria-invalid={invalide}
                      onChange={(event) => setModifs((m) => ({ ...m, [ligne.matiere_id]: event.target.value }))}
                      className="ml-auto h-8 w-20 text-right tabular-nums"
                    />
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-muted-foreground">
          {invalides.length > 0 ? (
            <span className="text-destructive">Un coefficient doit être compris entre 0,5 et 20.</span>
          ) : (
            <>
              Total des coefficients : <strong className="text-foreground">{formatNombre(totalCoefficients)}</strong>.
              Laissez vide une matière non enseignée.
            </>
          )}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setModifs({})} disabled={modifiees.length === 0 || enregistrement.isPending}>
            <RotateCcw />
            Annuler
          </Button>
          <Button onClick={enregistrer} disabled={modifiees.length === 0 || invalides.length > 0 || enregistrement.isPending}>
            {enregistrement.isPending ? <Loader2 className="animate-spin" /> : <Save />}
            Enregistrer{modifiees.length > 0 && ` (${modifiees.length})`}
          </Button>
        </div>
      </div>
    </div>
  )
}
