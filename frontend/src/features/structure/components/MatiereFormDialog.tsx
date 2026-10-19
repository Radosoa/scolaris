import { BookOpen } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { matieres } from '../api'
import { matiereSchema, type MatiereValues } from '../schemas'
import type { Matiere } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  matiere: Matiere | null
}

export function MatiereFormDialog({ open, onOpenChange, matiere }: Props) {
  const save = matieres.useSave()
  const form = useForm<MatiereValues>({ resolver: zodResolver(matiereSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (open) form.reset(matiere ? { nom: matiere.nom, code: matiere.code } : { nom: '', code: '' })
  }, [open, matiere, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await save.mutateAsync({ id: matiere?.id, donnees: valeurs })
      toast.success(matiere ? 'Matière modifiée.' : 'Matière créée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      icone={BookOpen}
      open={open}
      onOpenChange={onOpenChange}
      title={matiere ? `Modifier ${matiere.nom}` : 'Nouvelle matière'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
        <Field label="Nom" htmlFor="nom" error={errors.nom?.message}>
          <Input id="nom" aria-invalid={!!errors.nom} {...form.register('nom')} />
        </Field>
        <Field label="Code" htmlFor="code" error={errors.code?.message} hint="Ex. MATH">
          <Input id="code" className="uppercase" aria-invalid={!!errors.code} {...form.register('code')} />
        </Field>
      </div>
    </FormDialog>
  )
}
