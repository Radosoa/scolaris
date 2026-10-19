import { CalendarRange } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { annees } from '../api'
import { anneeSchema, type AnneeValues } from '../schemas'
import type { AnneeScolaire } from '../types'

function valeursParDefaut(annee: AnneeScolaire | null): AnneeValues {
  if (annee) {
    return { libelle: annee.libelle, date_debut: annee.date_debut, date_fin: annee.date_fin, active: annee.active }
  }
  const debut = new Date().getMonth() >= 7 ? new Date().getFullYear() : new Date().getFullYear() - 1
  return { libelle: `${debut}-${debut + 1}`, date_debut: `${debut}-09-01`, date_fin: `${debut + 1}-07-04`, active: false }
}

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  annee: AnneeScolaire | null
}

export function AnneeFormDialog({ open, onOpenChange, annee }: Props) {
  const save = annees.useSave()
  const form = useForm<AnneeValues>({ resolver: zodResolver(anneeSchema), defaultValues: valeursParDefaut(annee) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (open) form.reset(valeursParDefaut(annee))
  }, [open, annee, form])

  const onSubmit = form.handleSubmit(async ({ active, ...valeurs }) => {
    try {
      // L'activation d'une année existante passe par l'action dédiée « Activer »
      await save.mutateAsync({ id: annee?.id, donnees: annee ? valeurs : { ...valeurs, active } })
      toast.success(annee ? 'Année scolaire modifiée.' : 'Année scolaire créée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={CalendarRange}
      open={open}
      onOpenChange={onOpenChange}
      title={annee ? `Modifier ${annee.libelle}` : 'Nouvelle année scolaire'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <Field label="Libellé" htmlFor="libelle" error={errors.libelle?.message} hint="Ex. 2025-2026">
        <Input id="libelle" aria-invalid={!!errors.libelle} {...form.register('libelle')} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Date de début" htmlFor="date_debut" error={errors.date_debut?.message}>
          <Input id="date_debut" type="date" aria-invalid={!!errors.date_debut} {...form.register('date_debut')} />
        </Field>
        <Field label="Date de fin" htmlFor="date_fin" error={errors.date_fin?.message}>
          <Input id="date_fin" type="date" aria-invalid={!!errors.date_fin} {...form.register('date_fin')} />
        </Field>
      </div>
      {!annee && (
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="size-4 accent-primary" {...form.register('active')} />
          Définir comme année active (désactive l’année en cours)
        </label>
      )}
    </FormDialog>
  )
}
