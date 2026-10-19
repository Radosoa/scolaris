import { Users } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { tuteurs } from '../api'
import { tuteurSchema, type TuteurValues } from '../schemas'
import type { Tuteur } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  tuteur: Tuteur | null
  /** Valeurs proposées à la création (ex. nom de famille de l'élève) */
  suggestion?: Partial<TuteurValues>
  onSaved?: (tuteur: Tuteur) => void
}

export function TuteurFormDialog({ open, onOpenChange, tuteur, suggestion, onSaved }: Props) {
  const save = tuteurs.useSave()
  const form = useForm<TuteurValues>({ resolver: zodResolver(tuteurSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (!open) return
    form.reset(
      tuteur
        ? { nom: tuteur.nom, prenom: tuteur.prenom, telephone: tuteur.telephone, email: tuteur.email ?? '' }
        : { nom: '', prenom: '', telephone: '', email: '', ...suggestion },
    )
  }, [open, tuteur, suggestion, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      const enregistre = await save.mutateAsync({
        id: tuteur?.id,
        donnees: { ...valeurs, email: valeurs.email || undefined },
      })
      toast.success(tuteur ? 'Parent modifié.' : 'Parent créé.')
      onOpenChange(false)
      onSaved?.(enregistre)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={Users}
      open={open}
      onOpenChange={onOpenChange}
      title={tuteur ? `Modifier ${tuteur.nom_complet}` : 'Nouveau parent'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Nom" htmlFor="tuteur-nom" error={errors.nom?.message}>
          <Input id="tuteur-nom" aria-invalid={!!errors.nom} {...form.register('nom')} />
        </Field>
        <Field label="Prénom" htmlFor="tuteur-prenom" error={errors.prenom?.message}>
          <Input id="tuteur-prenom" aria-invalid={!!errors.prenom} {...form.register('prenom')} />
        </Field>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Téléphone" htmlFor="tuteur-telephone" error={errors.telephone?.message}>
          <Input id="tuteur-telephone" type="tel" aria-invalid={!!errors.telephone} {...form.register('telephone')} />
        </Field>
        <Field label="E-mail (facultatif)" htmlFor="tuteur-email" error={errors.email?.message}>
          <Input id="tuteur-email" type="email" aria-invalid={!!errors.email} {...form.register('email')} />
        </Field>
      </div>
    </FormDialog>
  )
}
