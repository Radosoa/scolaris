import { School } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { SelectField } from '@/components/form/SelectField'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { annees, classes, niveaux, TOUS } from '../api'
import { classeSchema, type ClasseValues } from '../schemas'
import type { Classe } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  classe: Classe | null
  /** Année proposée pour une nouvelle classe (année active ou filtre courant) */
  anneeParDefaut?: number
}

export function ClasseFormDialog({ open, onOpenChange, classe, anneeParDefaut }: Props) {
  const save = classes.useSave()
  const listeNiveaux = niveaux.useList(TOUS)
  const listeAnnees = annees.useList(TOUS)
  const form = useForm<ClasseValues>({ resolver: zodResolver(classeSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (!open) return
    form.reset(
      classe
        ? { nom: classe.nom, niveau_id: classe.niveau_id, annee_scolaire_id: classe.annee_scolaire_id, capacite: classe.capacite }
        : { nom: '', capacite: 32, annee_scolaire_id: anneeParDefaut } as ClasseValues,
    )
  }, [open, classe, anneeParDefaut, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await save.mutateAsync({ id: classe?.id, donnees: valeurs })
      toast.success(classe ? 'Classe modifiée.' : 'Classe créée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={School}
      open={open}
      onOpenChange={onOpenChange}
      title={classe ? `Modifier ${classe.nom}` : 'Nouvelle classe'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
        <Field label="Nom" htmlFor="nom" error={errors.nom?.message} hint="Ex. 6e A">
          <Input id="nom" aria-invalid={!!errors.nom} {...form.register('nom')} />
        </Field>
        <Field label="Capacité" htmlFor="capacite" error={errors.capacite?.message}>
          <Input
            id="capacite"
            type="number"
            min={1}
            max={200}
            aria-invalid={!!errors.capacite}
            {...form.register('capacite', { valueAsNumber: true })}
          />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Niveau" htmlFor="niveau_id" error={errors.niveau_id?.message}>
          <Controller
            control={form.control}
            name="niveau_id"
            render={({ field }) => (
              <SelectField
                id="niveau_id"
                value={field.value}
                onChange={field.onChange}
                invalid={!!errors.niveau_id}
                options={listeNiveaux.data?.data.map((n) => ({ value: n.id, label: n.libelle })) ?? []}
              />
            )}
          />
        </Field>
        <Field label="Année scolaire" htmlFor="annee_scolaire_id" error={errors.annee_scolaire_id?.message}>
          <Controller
            control={form.control}
            name="annee_scolaire_id"
            render={({ field }) => (
              <SelectField
                id="annee_scolaire_id"
                value={field.value}
                onChange={field.onChange}
                invalid={!!errors.annee_scolaire_id}
                options={
                  listeAnnees.data?.data.map((a) => ({
                    value: a.id,
                    label: a.active ? `${a.libelle} (active)` : a.libelle,
                  })) ?? []
                }
              />
            )}
          />
        </Field>
      </div>
    </FormDialog>
  )
}
