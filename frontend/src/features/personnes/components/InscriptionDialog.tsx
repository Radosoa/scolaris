import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { classes, useAnneeActive } from '@/features/structure/api'
import { applyValidationErrors, getErrorMessage } from '@/lib/api'
import { useEnregistrerInscription } from '../api'
import { STATUTS, type Inscription, type StatutInscription } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  eleveId: number
  nomEleve: string
  /** Inscription existante à modifier (classe / statut), sinon nouvelle inscription */
  inscription: Inscription | null
}

/** Inscription dans une classe de l'année active, ou changement de classe / de statut. */
export function InscriptionDialog({ open, onOpenChange, eleveId, nomEleve, inscription }: Props) {
  const anneeActive = useAnneeActive({ enabled: open })
  const anneeId = inscription?.annee_scolaire_id ?? anneeActive.data?.id
  const listeClasses = classes.useList({ annee_scolaire_id: anneeId, per_page: 100 }, { enabled: open && !!anneeId })
  const enregistrer = useEnregistrerInscription()

  const [classeId, setClasseId] = useState<string>('')
  const [statut, setStatut] = useState<StatutInscription>('inscrit')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  // À chaque ouverture, repartir de l'inscription affichée (ajustement pendant le rendu, sans effet)
  const [ouvertPrecedent, setOuvertPrecedent] = useState(false)
  if (open !== ouvertPrecedent) {
    setOuvertPrecedent(open)
    if (open) {
      setClasseId(inscription ? String(inscription.classe_id) : '')
      setStatut(inscription?.statut ?? 'inscrit')
      setErreurs({})
    }
  }

  const valider = async () => {
    if (!classeId) {
      setErreurs({ classe_id: 'Choisissez une classe.' })
      return
    }
    try {
      await enregistrer.mutateAsync({
        id: inscription?.id,
        donnees: inscription ? { classe_id: Number(classeId), statut } : { eleve_id: eleveId, classe_id: Number(classeId) },
      })
      toast.success(inscription ? 'Inscription mise à jour.' : `${nomEleve} est inscrit(e).`)
      onOpenChange(false)
    } catch (error) {
      const champs: Record<string, string> = {}
      const appliquees = applyValidationErrors(error, (champ, { message }) => {
        champs[String(champ)] = message ?? ''
      })
      if (appliquees) setErreurs(champs)
      else toast.error(getErrorMessage(error))
    }
  }

  const aucuneAnnee = !inscription && anneeActive.data === null

  return (
    <Dialog open={open} onOpenChange={(ouvert) => !enregistrer.isPending && onOpenChange(ouvert)}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{inscription ? 'Modifier l’inscription' : 'Inscrire dans une classe'}</DialogTitle>
          <DialogDescription>
            {nomEleve}
            {anneeActive.data && !inscription && ` · année ${anneeActive.data.libelle}`}
          </DialogDescription>
        </DialogHeader>

        {aucuneAnnee ? (
          <p className="text-sm text-destructive">Aucune année scolaire active : activez une année avant d’inscrire des élèves.</p>
        ) : (
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="inscription-classe">Classe</Label>
              <Select value={classeId} onValueChange={setClasseId}>
                <SelectTrigger id="inscription-classe" className="w-full" aria-invalid={!!erreurs.classe_id}>
                  <SelectValue placeholder="Choisir une classe" />
                </SelectTrigger>
                <SelectContent>
                  {listeClasses.data?.data.map((c) => {
                    const complete = (c.effectif ?? 0) >= c.capacite && c.id !== inscription?.classe_id
                    return (
                      <SelectItem key={c.id} value={String(c.id)} disabled={complete}>
                        {c.nom} — {c.effectif}/{c.capacite}
                        {complete && ' (complète)'}
                      </SelectItem>
                    )
                  })}
                </SelectContent>
              </Select>
              {(erreurs.classe_id || erreurs.eleve_id) && (
                <p className="text-sm text-destructive">{erreurs.classe_id ?? erreurs.eleve_id}</p>
              )}
            </div>

            {inscription && (
              <div className="space-y-2">
                <Label htmlFor="inscription-statut">Statut</Label>
                <Select value={statut} onValueChange={(valeur) => setStatut(valeur as StatutInscription)}>
                  <SelectTrigger id="inscription-statut" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(STATUTS).map(([valeur, libelle]) => (
                      <SelectItem key={valeur} value={valeur}>
                        {libelle}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">Un élève transféré ou en abandon libère sa place dans la classe.</p>
              </div>
            )}
          </div>
        )}

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={enregistrer.isPending}>
            Annuler
          </Button>
          <Button onClick={valider} disabled={enregistrer.isPending || aucuneAnnee}>
            {enregistrer.isPending && <Loader2 className="animate-spin" />}
            {inscription ? 'Enregistrer' : 'Inscrire'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
