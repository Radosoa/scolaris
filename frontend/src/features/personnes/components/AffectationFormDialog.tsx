import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { SelectField } from '@/components/form/SelectField'
import { classes, useAnneeActive, useCoefficients } from '@/features/structure/api'
import { handleFormError } from '@/lib/form-errors'
import { affectations, enseignants } from '../api'
import type { Affectation } from '../types'

const schema = z.object({
  classe_id: z.number({ error: 'Choisissez une classe.' }),
  matiere_id: z.number({ error: 'Choisissez une matière.' }),
  enseignant_id: z.number({ error: 'Choisissez un enseignant.' }),
})

type Valeurs = z.infer<typeof schema>

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  affectation: Affectation | null
  classeParDefaut?: number
}

export function AffectationFormDialog({ open, onOpenChange, affectation, classeParDefaut }: Props) {
  const save = affectations.useSave()
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: open && !!anneeActive.data })
  const listeEnseignants = enseignants.useList({ per_page: 100 }, { enabled: open })
  const form = useForm<Valeurs>({ resolver: zodResolver(schema) })
  const { errors, isSubmitting } = form.formState

  // Seules les matières enseignées au niveau de la classe (coefficient défini) sont proposées
  const classeId = useWatch({ control: form.control, name: 'classe_id' })
  const niveauId = listeClasses.data?.data.find((c) => c.id === classeId)?.niveau_id
  const grille = useCoefficients(niveauId)
  const matieres = (grille.data ?? []).filter((ligne) => ligne.valeur !== null)

  useEffect(() => {
    if (!open) return
    form.reset(
      affectation
        ? { classe_id: affectation.classe_id, matiere_id: affectation.matiere_id, enseignant_id: affectation.enseignant_id }
        : ({ classe_id: classeParDefaut } as Valeurs),
    )
  }, [open, affectation, classeParDefaut, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await save.mutateAsync({ id: affectation?.id, donnees: valeurs })
      toast.success(affectation ? 'Affectation modifiée.' : 'Affectation créée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={affectation ? 'Modifier l’affectation' : 'Nouvelle affectation'}
      description="Un seul enseignant par matière et par classe."
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <Field label="Classe" htmlFor="aff-classe" error={errors.classe_id?.message}>
        <Controller
          control={form.control}
          name="classe_id"
          render={({ field }) => (
            <SelectField
              id="aff-classe"
              value={field.value}
              onChange={(valeur) => {
                field.onChange(valeur)
                form.resetField('matiere_id', { defaultValue: undefined as unknown as number })
              }}
              invalid={!!errors.classe_id}
              options={listeClasses.data?.data.map((c) => ({ value: c.id, label: c.nom })) ?? []}
            />
          )}
        />
      </Field>
      <Field label="Matière" htmlFor="aff-matiere" error={errors.matiere_id?.message}>
        <Controller
          control={form.control}
          name="matiere_id"
          render={({ field }) => (
            <SelectField
              id="aff-matiere"
              value={field.value}
              onChange={field.onChange}
              invalid={!!errors.matiere_id}
              disabled={!classeId}
              placeholder={classeId ? 'Choisir…' : 'Choisissez d’abord une classe'}
              options={matieres.map((m) => ({ value: m.matiere_id, label: `${m.nom} (coef. ${m.valeur})` }))}
            />
          )}
        />
      </Field>
      <Field label="Enseignant" htmlFor="aff-enseignant" error={errors.enseignant_id?.message}>
        <Controller
          control={form.control}
          name="enseignant_id"
          render={({ field }) => (
            <SelectField
              id="aff-enseignant"
              value={field.value}
              onChange={field.onChange}
              invalid={!!errors.enseignant_id}
              options={
                listeEnseignants.data?.data.map((e) => ({
                  value: e.id,
                  label: e.specialite ? `${e.nom_complet} — ${e.specialite}` : e.nom_complet,
                })) ?? []
              }
            />
          )}
        />
      </Field>
    </FormDialog>
  )
}
