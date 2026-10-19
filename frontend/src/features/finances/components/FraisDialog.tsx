import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { useEnregistrerFrais } from '../api'
import { deviseActive, lireMontant, montantEnSaisie } from '../format'
import { fraisSchema, type FraisValues } from '../schemas'
import type { LigneFrais } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Ligne modifiée, ou null pour une création dans ce niveau */
  frais: LigneFrais | null
  niveau: { id: number; libelle: string } | null
  anneeId: number
}

export function FraisDialog({ open, onOpenChange, frais, niveau, anneeId }: Props) {
  const enregistrer = useEnregistrerFrais()
  const form = useForm<FraisValues>({ resolver: zodResolver(fraisSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (open) form.reset({ libelle: frais?.libelle ?? '', montant: frais ? montantEnSaisie(frais.montant) : '' })
  }, [open, frais, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await enregistrer.mutateAsync({
        id: frais?.id,
        libelle: valeurs.libelle,
        montant: lireMontant(valeurs.montant)!,
        ...(frais ? {} : { niveau_id: niveau!.id, annee_scolaire_id: anneeId }),
      })
      toast.success(frais ? 'Frais modifiés.' : 'Frais ajoutés.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={frais ? `Modifier « ${frais.libelle} »` : `Nouveaux frais — ${niveau?.libelle ?? ''}`}
      description="Montant annuel par élève du niveau."
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
        <Field label="Libellé" htmlFor="frais-libelle" error={errors.libelle?.message}>
          <Input id="frais-libelle" placeholder="Ex. Scolarité, Cantine" aria-invalid={!!errors.libelle} {...form.register('libelle')} />
        </Field>
        <Field label={`Montant (${deviseActive().symbole})`} htmlFor="frais-montant" error={errors.montant?.message}>
          <Input id="frais-montant" inputMode="decimal" className="text-right" aria-invalid={!!errors.montant} {...form.register('montant')} />
        </Field>
      </div>
    </FormDialog>
  )
}
