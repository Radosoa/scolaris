import { Loader2, Plus, UserPlus, X } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useDialogState } from '@/hooks/use-dialog-state'
import { getErrorMessage } from '@/lib/api'
import { eleves, tuteurs } from '../api'
import { LIENS, type Eleve, type LienParente, type Tuteur } from '../types'
import { TuteurFormDialog } from './TuteurFormDialog'

interface Lien {
  tuteur_id: number
  nom_complet: string
  telephone: string
  lien: LienParente
}

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  eleve: Eleve
}

/** Ajoute, retire ou requalifie les parents d'un élève, puis enregistre en une fois. */
export function ParentsDialog({ open, onOpenChange, eleve }: Props) {
  const save = eleves.useSave()
  const [liens, setLiens] = useState<Lien[]>([])
  const [recherche, setRecherche] = useState('')
  const q = useDebouncedValue(recherche.trim())
  const resultats = tuteurs.useList({ q, per_page: 6 }, { enabled: open && q.length >= 2 })
  const creation = useDialogState<null>()

  // À chaque ouverture, repartir des parents actuels (ajustement pendant le rendu, sans effet)
  const [ouvertPrecedent, setOuvertPrecedent] = useState(false)
  if (open !== ouvertPrecedent) {
    setOuvertPrecedent(open)
    if (open) {
      setLiens(
        (eleve.tuteurs ?? []).map((t) => ({ tuteur_id: t.id, nom_complet: t.nom_complet, telephone: t.telephone, lien: t.lien })),
      )
      setRecherche('')
    }
  }

  const ajouter = (tuteur: Tuteur) => {
    if (liens.some((l) => l.tuteur_id === tuteur.id)) return
    const lienParDefaut: LienParente = liens.some((l) => l.lien === 'mere') ? 'pere' : 'mere'
    setLiens([...liens, { tuteur_id: tuteur.id, nom_complet: tuteur.nom_complet, telephone: tuteur.telephone, lien: lienParDefaut }])
    setRecherche('')
  }

  const enregistrer = async () => {
    try {
      await save.mutateAsync({
        id: eleve.id,
        donnees: {
          nom: eleve.nom,
          prenom: eleve.prenom,
          sexe: eleve.sexe,
          date_naissance: eleve.date_naissance,
          lieu_naissance: eleve.lieu_naissance ?? undefined,
          tuteurs: liens.map(({ tuteur_id, lien }) => ({ tuteur_id, lien })),
        },
      })
      toast.success('Parents enregistrés.')
      onOpenChange(false)
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  const proposes = (resultats.data?.data ?? []).filter((t) => !liens.some((l) => l.tuteur_id === t.id))

  return (
    <>
      <Dialog open={open} onOpenChange={(ouvert) => !save.isPending && onOpenChange(ouvert)}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Parents de {eleve.nom_complet}</DialogTitle>
            <DialogDescription>Recherchez un parent existant ou créez-en un nouveau.</DialogDescription>
          </DialogHeader>

          <div className="space-y-2">
            {liens.length === 0 && <p className="text-sm text-muted-foreground">Aucun parent rattaché.</p>}
            {liens.map((lien) => (
              <div key={lien.tuteur_id} className="flex items-center gap-2 rounded-lg border p-2">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{lien.nom_complet}</p>
                  <p className="text-xs text-muted-foreground">{lien.telephone}</p>
                </div>
                <Select
                  value={lien.lien}
                  onValueChange={(valeur) =>
                    setLiens(liens.map((l) => (l.tuteur_id === lien.tuteur_id ? { ...l, lien: valeur as LienParente } : l)))
                  }
                >
                  <SelectTrigger className="w-36" aria-label={`Lien de ${lien.nom_complet}`}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(LIENS).map(([valeur, libelle]) => (
                      <SelectItem key={valeur} value={valeur}>
                        {libelle}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label={`Retirer ${lien.nom_complet}`}
                  onClick={() => setLiens(liens.filter((l) => l.tuteur_id !== lien.tuteur_id))}
                >
                  <X />
                </Button>
              </div>
            ))}
          </div>

          <div className="space-y-2">
            <Label htmlFor="recherche-parent">Ajouter un parent existant</Label>
            <Input
              id="recherche-parent"
              type="search"
              placeholder="Nom, prénom ou téléphone (2 caractères min.)"
              value={recherche}
              onChange={(event) => setRecherche(event.target.value)}
            />
            {q.length >= 2 && (
              <div className="max-h-48 overflow-y-auto rounded-lg border">
                {resultats.isFetching && <p className="p-2 text-sm text-muted-foreground">Recherche…</p>}
                {!resultats.isFetching && proposes.length === 0 && (
                  <p className="p-2 text-sm text-muted-foreground">Aucun parent trouvé.</p>
                )}
                {proposes.map((tuteur) => (
                  <button
                    key={tuteur.id}
                    type="button"
                    onClick={() => ajouter(tuteur)}
                    className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                  >
                    <span>
                      {tuteur.nom_complet}
                      <span className="ml-2 text-xs text-muted-foreground">{tuteur.telephone}</span>
                    </span>
                    <Plus className="size-4 text-muted-foreground" />
                  </button>
                ))}
              </div>
            )}
            <Button variant="outline" size="sm" onClick={() => creation.ouvrir(null)}>
              <UserPlus />
              Créer un nouveau parent
            </Button>
          </div>

          <DialogFooter>
            <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={save.isPending}>
              Annuler
            </Button>
            <Button onClick={enregistrer} disabled={save.isPending}>
              {save.isPending && <Loader2 className="animate-spin" />}
              Enregistrer
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <TuteurFormDialog
        open={creation.open}
        onOpenChange={creation.setOpen}
        tuteur={null}
        suggestion={{ nom: eleve.nom }}
        onSaved={ajouter}
      />
    </>
  )
}
