import { useState } from 'react'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useAnnulerPaiement } from '../api'
import { formatMontant } from '../format'
import type { Paiement } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  paiement: Paiement | null
}

/** Annulation motivée d'un paiement : il sort des totaux, son reçu reste consultable (« ANNULÉ »). */
export function AnnulationDialog({ open, onOpenChange, paiement }: Props) {
  const annuler = useAnnulerPaiement()
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string>()

  // Formulaire vidé à chaque ouverture (ajustement pendant le rendu)
  const [ouverturePrecedente, setOuverturePrecedente] = useState(open)
  if (open !== ouverturePrecedente) {
    setOuverturePrecedente(open)
    if (open) {
      setMotif('')
      setErreur(undefined)
    }
  }

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={`Annuler le paiement ${paiement?.numero_recu ?? ''}`}
      description={paiement ? `${formatMontant(paiement.montant)} du ${formatDate(paiement.date)}. Le reçu restera consultable avec la mention « Annulé ».` : undefined}
      submitLabel="Annuler le paiement"
      isSubmitting={annuler.isPending}
      onSubmit={async (e) => {
        e.preventDefault()
        if (!motif.trim()) {
          setErreur('Indiquez le motif de l’annulation.')
          return
        }
        try {
          await annuler.mutateAsync({ id: paiement!.id, motif: motif.trim() })
          toast.success('Paiement annulé.')
          onOpenChange(false)
        } catch (error) {
          toast.error(getErrorMessage(error))
        }
      }}
    >
      <Field label="Motif" htmlFor="annulation-motif" error={erreur} hint="Ex. chèque rejeté, erreur de saisie.">
        <Input id="annulation-motif" value={motif} aria-invalid={!!erreur} onChange={(e) => setMotif(e.target.value)} />
      </Field>
    </FormDialog>
  )
}
