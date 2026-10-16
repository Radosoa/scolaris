import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { enseignants } from '../api'
import { enseignantSchema, motDePasse, type EnseignantValues } from '../schemas'
import type { Enseignant } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  enseignant: Enseignant | null
}

/** À la création, le mot de passe initial devient obligatoire. */
const creationSchema = enseignantSchema.extend({ password: motDePasse })

export function EnseignantFormDialog({ open, onOpenChange, enseignant }: Props) {
  const save = enseignants.useSave()
  const form = useForm<EnseignantValues>({ resolver: zodResolver(enseignant ? enseignantSchema : creationSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (!open) return
    form.reset(
      enseignant
        ? {
            nom: enseignant.nom,
            prenom: enseignant.prenom,
            specialite: enseignant.specialite ?? '',
            telephone: enseignant.telephone ?? '',
            email: enseignant.email ?? '',
          }
        : { nom: '', prenom: '', specialite: '', telephone: '', email: '', password: '' },
    )
  }, [open, enseignant, form])

  const onSubmit = form.handleSubmit(async ({ password, ...valeurs }) => {
    try {
      await save.mutateAsync({
        id: enseignant?.id,
        donnees: {
          ...valeurs,
          specialite: valeurs.specialite || undefined,
          telephone: valeurs.telephone || undefined,
          ...(enseignant ? {} : { password }),
        },
      })
      toast.success(enseignant ? 'Enseignant modifié.' : 'Enseignant créé avec son compte de connexion.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={enseignant ? `Modifier ${enseignant.nom_complet}` : 'Nouvel enseignant'}
      description={enseignant ? undefined : 'Un compte « professeur » est créé avec ces identifiants.'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Nom" htmlFor="ens-nom" error={errors.nom?.message}>
          <Input id="ens-nom" aria-invalid={!!errors.nom} {...form.register('nom')} />
        </Field>
        <Field label="Prénom" htmlFor="ens-prenom" error={errors.prenom?.message}>
          <Input id="ens-prenom" aria-invalid={!!errors.prenom} {...form.register('prenom')} />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Spécialité" htmlFor="ens-specialite" error={errors.specialite?.message}>
          <Input id="ens-specialite" {...form.register('specialite')} />
        </Field>
        <Field label="Téléphone" htmlFor="ens-telephone" error={errors.telephone?.message}>
          <Input id="ens-telephone" type="tel" aria-invalid={!!errors.telephone} {...form.register('telephone')} />
        </Field>
      </div>
      <Field label="E-mail de connexion" htmlFor="ens-email" error={errors.email?.message}>
        <Input id="ens-email" type="email" autoComplete="off" aria-invalid={!!errors.email} {...form.register('email')} />
      </Field>
      {!enseignant && (
        <Field
          label="Mot de passe initial"
          htmlFor="ens-password"
          error={errors.password?.message}
          hint="8 caractères minimum, avec des lettres et des chiffres."
        >
          <Input
            id="ens-password"
            type="password"
            autoComplete="new-password"
            aria-invalid={!!errors.password}
            {...form.register('password')}
          />
        </Field>
      )}
    </FormDialog>
  )
}
