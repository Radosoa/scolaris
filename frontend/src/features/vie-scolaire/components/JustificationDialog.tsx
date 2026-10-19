import { FileCheck2 } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { formatDate } from '@/lib/format'
import { handleFormError } from '@/lib/form-errors'
import { useJustifier } from '../api'
import { justificationSchema, MOTIFS, type JustificationValues } from '../schemas'
import type { Absence } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  absence: Absence | null
}

/** Justification par la vie scolaire, pour une absence ou toute la journée de l'élève. */
export function JustificationDialog({ open, onOpenChange, absence }: Props) {
  const justifier = useJustifier()
  const form = useForm<JustificationValues>({ resolver: zodResolver(justificationSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (open) form.reset({ justifiee: true, motif: absence?.motif ?? '', toute_la_journee: absence?.type === 'absence' })
  }, [open, absence, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      const { meta } = await justifier.mutateAsync({ id: absence!.id, ...valeurs, motif: valeurs.motif || null })
      toast.success(meta.mises_a_jour > 1 ? `${meta.mises_a_jour} absences justifiées.` : 'Justification enregistrée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={FileCheck2}
      open={open}
      onOpenChange={onOpenChange}
      title={absence?.type === 'retard' ? 'Justifier le retard' : 'Justifier l’absence'}
      description={
        absence
          ? `${absence.eleve?.prenom} ${absence.eleve?.nom} · ${formatDate(absence.date)}, ${absence.heure_debut} – ${absence.heure_fin}${absence.matiere ? ` · ${absence.matiere}` : ''}`
          : undefined
      }
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
      submitLabel="Justifier"
    >
      <Field label="Motif" htmlFor="justification-motif" error={errors.motif?.message}>
        <Input id="justification-motif" aria-invalid={!!errors.motif} {...form.register('motif')} />
      </Field>
      <div className="flex flex-wrap gap-1.5">
        {MOTIFS.map((motif) => (
          <button key={motif} type="button" onClick={() => form.setValue('motif', motif, { shouldValidate: true })}>
            <Badge variant="outline" className="cursor-pointer hover:bg-accent">
              {motif}
            </Badge>
          </button>
        ))}
      </div>
      <Controller
        control={form.control}
        name="toute_la_journee"
        render={({ field }) => (
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 accent-primary" checked={field.value} onChange={(e) => field.onChange(e.target.checked)} />
            Appliquer à toutes les absences et retards de l’élève ce jour-là
          </label>
        )}
      />
    </FormDialog>
  )
}
