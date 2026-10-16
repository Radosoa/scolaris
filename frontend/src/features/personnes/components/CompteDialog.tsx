import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { useCreerCompte } from '../api'
import { compteSchema, type CompteValues } from '../schemas'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  type: 'eleves' | 'tuteurs'
  id: number
  nom: string
  emailSuggere?: string | null
}

/** Crée le compte de connexion d'un élève ou d'un parent. */
export function CompteDialog({ open, onOpenChange, type, id, nom, emailSuggere }: Props) {
  const creer = useCreerCompte(type, id)
  const form = useForm<CompteValues>({ resolver: zodResolver(compteSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (open) form.reset({ email: emailSuggere ?? '', password: '' })
  }, [open, emailSuggere, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await creer.mutateAsync(valeurs)
      toast.success(`Compte créé pour ${nom}. Communiquez-lui ses identifiants.`)
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={`Créer le compte de ${nom}`}
      description={
        type === 'eleves'
          ? 'L’élève pourra consulter ses notes, son emploi du temps et ses bulletins.'
          : 'Le parent pourra suivre la scolarité de ses enfants.'
      }
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
      submitLabel="Créer le compte"
    >
      <Field label="E-mail de connexion" htmlFor="compte-email" error={errors.email?.message}>
        <Input id="compte-email" type="email" autoComplete="off" aria-invalid={!!errors.email} {...form.register('email')} />
      </Field>
      <Field
        label="Mot de passe initial"
        htmlFor="compte-password"
        error={errors.password?.message}
        hint="8 caractères minimum, avec des lettres et des chiffres."
      >
        <Input
          id="compte-password"
          type="password"
          autoComplete="new-password"
          aria-invalid={!!errors.password}
          {...form.register('password')}
        />
      </Field>
    </FormDialog>
  )
}
