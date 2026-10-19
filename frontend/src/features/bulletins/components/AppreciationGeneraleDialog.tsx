import { MessageSquareText } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Textarea } from '@/components/ui/textarea'
import { handleFormError } from '@/lib/form-errors'
import { useAppreciationGenerale } from '../api'
import { appreciationGeneraleSchema, MAX_APPRECIATION_GENERALE, type AppreciationGeneraleValues } from '../schemas'
import type { Bulletin } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  bulletin: Bulletin | null
}

/** Appréciation générale de la direction (bulletin non validé). */
export function AppreciationGeneraleDialog({ open, onOpenChange, bulletin }: Props) {
  // Le hook a besoin d'un identifiant même quand la modale est fermée
  const enregistrer = useAppreciationGenerale(bulletin?.id ?? 0)
  const form = useForm<AppreciationGeneraleValues>({ resolver: zodResolver(appreciationGeneraleSchema) })
  const { errors, isSubmitting } = form.formState
  const longueur = useWatch({ control: form.control, name: 'appreciation_generale' })?.length ?? 0

  useEffect(() => {
    if (open) form.reset({ appreciation_generale: bulletin?.appreciation_generale ?? '' })
  }, [open, bulletin, form])

  const onSubmit = form.handleSubmit(async ({ appreciation_generale }) => {
    try {
      await enregistrer.mutateAsync(appreciation_generale)
      toast.success('Appréciation enregistrée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  const eleve = bulletin?.eleve

  return (
    <FormDialog
      icone={MessageSquareText}
      open={open}
      onOpenChange={onOpenChange}
      title="Appréciation générale"
      description={eleve ? `${eleve.prenom} ${eleve.nom} · moyenne ${bulletin?.moyenne?.toLocaleString('fr-FR') ?? '—'}` : undefined}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <Field
        label="Appréciation"
        htmlFor="appreciation_generale"
        error={errors.appreciation_generale?.message}
        hint={`${longueur} / ${MAX_APPRECIATION_GENERALE} caractères · laisser vide pour effacer`}
      >
        <Textarea
          id="appreciation_generale"
          rows={4}
          aria-invalid={!!errors.appreciation_generale}
          {...form.register('appreciation_generale')}
        />
      </Field>
    </FormDialog>
  )
}
