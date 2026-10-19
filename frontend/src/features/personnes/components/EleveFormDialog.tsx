import { GraduationCap } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { handleFormError } from '@/lib/form-errors'
import { eleves } from '../api'
import { eleveSchema, type EleveValues } from '../schemas'
import type { Eleve } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  eleve: Eleve | null
  onSaved?: (eleve: Eleve) => void
}

export function EleveFormDialog({ open, onOpenChange, eleve, onSaved }: Props) {
  const save = eleves.useSave()
  const form = useForm<EleveValues>({ resolver: zodResolver(eleveSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (!open) return
    form.reset(
      eleve
        ? {
            nom: eleve.nom,
            prenom: eleve.prenom,
            sexe: eleve.sexe,
            date_naissance: eleve.date_naissance,
            lieu_naissance: eleve.lieu_naissance ?? '',
          }
        : ({ nom: '', prenom: '', date_naissance: '', lieu_naissance: '' } as EleveValues),
    )
  }, [open, eleve, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      // Les liens avec les parents sont gérés depuis la fiche (champ « tuteurs » omis = inchangés)
      const enregistre = await save.mutateAsync({
        id: eleve?.id,
        donnees: { ...valeurs, lieu_naissance: valeurs.lieu_naissance || undefined },
      })
      toast.success(eleve ? 'Dossier mis à jour.' : `Élève créé (matricule ${enregistre.matricule}).`)
      onOpenChange(false)
      onSaved?.(enregistre)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={GraduationCap}
      open={open}
      onOpenChange={onOpenChange}
      title={eleve ? `Modifier ${eleve.nom_complet}` : 'Nouvel élève'}
      description={eleve ? `Matricule ${eleve.matricule}` : 'Le matricule est attribué automatiquement.'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Nom" htmlFor="nom" error={errors.nom?.message}>
          <Input id="nom" autoComplete="off" aria-invalid={!!errors.nom} {...form.register('nom')} />
        </Field>
        <Field label="Prénom" htmlFor="prenom" error={errors.prenom?.message}>
          <Input id="prenom" autoComplete="off" aria-invalid={!!errors.prenom} {...form.register('prenom')} />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-[9rem_1fr]">
        <Field label="Sexe" htmlFor="sexe" error={errors.sexe?.message}>
          <Controller
            control={form.control}
            name="sexe"
            render={({ field }) => (
              <Select value={field.value ?? ''} onValueChange={field.onChange}>
                <SelectTrigger id="sexe" className="w-full" aria-invalid={!!errors.sexe}>
                  <SelectValue placeholder="Choisir…" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="F">Fille</SelectItem>
                  <SelectItem value="M">Garçon</SelectItem>
                </SelectContent>
              </Select>
            )}
          />
        </Field>
        <Field label="Date de naissance" htmlFor="date_naissance" error={errors.date_naissance?.message}>
          <Input id="date_naissance" type="date" aria-invalid={!!errors.date_naissance} {...form.register('date_naissance')} />
        </Field>
      </div>
      <Field label="Lieu de naissance" htmlFor="lieu_naissance" error={errors.lieu_naissance?.message}>
        <Input id="lieu_naissance" {...form.register('lieu_naissance')} />
      </Field>
    </FormDialog>
  )
}
