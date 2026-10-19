import { ClipboardCheck } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { Periode } from '@/features/structure/types'
import { formatDate } from '@/lib/format'
import { handleFormError } from '@/lib/form-errors'
import { evaluations } from '../api'
import { TYPES_EVALUATION, type Evaluation, type TypeEvaluation } from '../types'

const schema = z.object({
  type: z.enum(['devoir', 'interrogation', 'composition'], { error: 'Choisissez un type.' }),
  titre: z.string().trim().min(1, 'Le titre est obligatoire.').max(150),
  date: z.string().min(1, 'La date est obligatoire.'),
  bareme: z.number({ error: 'Le barème est obligatoire.' }).min(1, 'Minimum 1.').max(100, 'Maximum 100.'),
})

type Valeurs = z.infer<typeof schema>

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  affectationId: number
  periode: Periode
  evaluation: Evaluation | null
  onSaved?: (evaluation: Evaluation) => void
}

export function EvaluationFormDialog({ open, onOpenChange, affectationId, periode, evaluation, onSaved }: Props) {
  const save = evaluations.useSave()
  const form = useForm<Valeurs>({ resolver: zodResolver(schema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (!open) return
    const aujourdhui = new Date().toISOString().slice(0, 10)
    const dateParDefaut = aujourdhui >= periode.date_debut && aujourdhui <= periode.date_fin ? aujourdhui : periode.date_debut
    form.reset(
      evaluation
        ? { type: evaluation.type, titre: evaluation.titre, date: evaluation.date, bareme: evaluation.bareme }
        : { type: 'devoir', titre: '', date: dateParDefaut, bareme: 20 },
    )
  }, [open, evaluation, periode, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      const enregistree = await save.mutateAsync({
        id: evaluation?.id,
        donnees: { ...valeurs, periode_id: periode.id, ...(evaluation ? {} : { affectation_id: affectationId }) },
      })
      toast.success(evaluation ? 'Évaluation modifiée.' : 'Évaluation créée.')
      onOpenChange(false)
      onSaved?.(enregistree)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={ClipboardCheck}
      open={open}
      onOpenChange={onOpenChange}
      title={evaluation ? `Modifier « ${evaluation.titre} »` : 'Nouvelle évaluation'}
      description={`${periode.libelle} : du ${formatDate(periode.date_debut)} au ${formatDate(periode.date_fin)}.`}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
      submitLabel={evaluation ? 'Enregistrer' : 'Créer et saisir les notes'}
    >
      <div className="grid gap-4 sm:grid-cols-[11rem_1fr]">
        <Field label="Type" htmlFor="eval-type" error={errors.type?.message}>
          <Controller
            control={form.control}
            name="type"
            render={({ field }) => (
              <Select value={field.value} onValueChange={(v) => field.onChange(v as TypeEvaluation)}>
                <SelectTrigger id="eval-type" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(TYPES_EVALUATION).map(([valeur, libelle]) => (
                    <SelectItem key={valeur} value={valeur}>
                      {libelle}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </Field>
        <Field label="Titre" htmlFor="eval-titre" error={errors.titre?.message}>
          <Input id="eval-titre" placeholder="Ex. Fractions et décimaux" aria-invalid={!!errors.titre} {...form.register('titre')} />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Date" htmlFor="eval-date" error={errors.date?.message}>
          <Input
            id="eval-date"
            type="date"
            min={periode.date_debut}
            max={periode.date_fin}
            aria-invalid={!!errors.date}
            {...form.register('date')}
          />
        </Field>
        <Field label="Barème (noté sur)" htmlFor="eval-bareme" error={errors.bareme?.message} hint="Ramené sur 20 dans le bulletin.">
          <Input
            id="eval-bareme"
            type="number"
            min={1}
            max={100}
            step="0.5"
            aria-invalid={!!errors.bareme}
            {...form.register('bareme', { valueAsNumber: true })}
          />
        </Field>
      </div>
    </FormDialog>
  )
}
