import { CalendarDays } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/format'
import { handleFormError } from '@/lib/form-errors'
import { periodes } from '../api'
import { periodeSchema, type PeriodeValues } from '../schemas'
import type { AnneeScolaire, Periode } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  annee: AnneeScolaire
  periode: Periode | null
  /** Ordre proposé pour une nouvelle période */
  ordreSuivant: number
}

export function PeriodeFormDialog({ open, onOpenChange, annee, periode, ordreSuivant }: Props) {
  const save = periodes.useSave()
  const form = useForm<PeriodeValues>({ resolver: zodResolver(periodeSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (!open) return
    form.reset(
      periode
        ? { libelle: periode.libelle, ordre: periode.ordre, date_debut: periode.date_debut, date_fin: periode.date_fin }
        : { libelle: `Trimestre ${ordreSuivant}`, ordre: ordreSuivant, date_debut: '', date_fin: '' },
    )
  }, [open, periode, ordreSuivant, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await save.mutateAsync({
        id: periode?.id,
        donnees: periode ? valeurs : { ...valeurs, annee_scolaire_id: annee.id },
      })
      toast.success(periode ? 'Période modifiée.' : 'Période créée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={CalendarDays}
      open={open}
      onOpenChange={onOpenChange}
      title={periode ? `Modifier ${periode.libelle}` : 'Nouvelle période'}
      description={`Année ${annee.libelle} : du ${formatDate(annee.date_debut)} au ${formatDate(annee.date_fin)}.`}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-[1fr_7rem]">
        <Field label="Libellé" htmlFor="libelle" error={errors.libelle?.message}>
          <Input id="libelle" aria-invalid={!!errors.libelle} {...form.register('libelle')} />
        </Field>
        <Field label="Ordre" htmlFor="ordre" error={errors.ordre?.message}>
          <Input
            id="ordre"
            type="number"
            min={1}
            max={12}
            aria-invalid={!!errors.ordre}
            {...form.register('ordre', { valueAsNumber: true })}
          />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Date de début" htmlFor="date_debut" error={errors.date_debut?.message}>
          <Input
            id="date_debut"
            type="date"
            min={annee.date_debut}
            max={annee.date_fin}
            aria-invalid={!!errors.date_debut}
            {...form.register('date_debut')}
          />
        </Field>
        <Field label="Date de fin" htmlFor="date_fin" error={errors.date_fin?.message}>
          <Input
            id="date_fin"
            type="date"
            min={annee.date_debut}
            max={annee.date_fin}
            aria-invalid={!!errors.date_fin}
            {...form.register('date_fin')}
          />
        </Field>
      </div>
    </FormDialog>
  )
}
