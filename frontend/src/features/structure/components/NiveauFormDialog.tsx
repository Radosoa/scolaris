import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Input } from '@/components/ui/input'
import { handleFormError } from '@/lib/form-errors'
import { niveaux } from '../api'
import { niveauSchema, type NiveauValues } from '../schemas'
import type { Niveau } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  niveau: Niveau | null
  ordreSuivant: number
}

export function NiveauFormDialog({ open, onOpenChange, niveau, ordreSuivant }: Props) {
  const save = niveaux.useSave()
  const form = useForm<NiveauValues>({ resolver: zodResolver(niveauSchema) })
  const { errors, isSubmitting } = form.formState

  useEffect(() => {
    if (open) form.reset(niveau ? { libelle: niveau.libelle, ordre: niveau.ordre } : { libelle: '', ordre: ordreSuivant })
  }, [open, niveau, ordreSuivant, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await save.mutateAsync({ id: niveau?.id, donnees: valeurs })
      toast.success(niveau ? 'Niveau modifié.' : 'Niveau créé.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={niveau ? `Modifier ${niveau.libelle}` : 'Nouveau niveau'}
      description="L’ordre détermine le classement des niveaux (6e = 1, 5e = 2…)."
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="grid gap-4 sm:grid-cols-[1fr_7rem]">
        <Field label="Libellé" htmlFor="libelle" error={errors.libelle?.message}>
          <Input id="libelle" aria-invalid={!!errors.libelle} {...form.register('libelle')} />
        </Field>
        <Field label="Ordre" htmlFor="ordre" error={errors.ordre?.message}>
          <Input
            id="ordre"
            type="number"
            min={1}
            aria-invalid={!!errors.ordre}
            {...form.register('ordre', { valueAsNumber: true })}
          />
        </Field>
      </div>
    </FormDialog>
  )
}
