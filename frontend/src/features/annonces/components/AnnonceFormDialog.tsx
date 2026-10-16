import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { SelectField } from '@/components/form/SelectField'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { classes, useAnneeActive } from '@/features/structure/api'
import { handleFormError } from '@/lib/form-errors'
import { annonces } from '../api'
import { CIBLES, type Annonce, type CibleAnnonce } from '../types'

const schema = z
  .object({
    titre: z.string().trim().min(1, 'Le titre est obligatoire.').max(150, '150 caractères au maximum.'),
    contenu: z.string().trim().min(1, 'Le message est obligatoire.').max(5000, '5 000 caractères au maximum.'),
    cible: z.enum(['tous', 'professeurs', 'parents', 'classe']),
    classe_id: z.number().optional(),
    // « AAAA-MM-JJTHH:MM » (champ datetime-local) ; vide = publication immédiate
    date_publication: z.string(),
  })
  .refine((v) => v.cible !== 'classe' || !!v.classe_id, { path: ['classe_id'], message: 'Choisissez la classe concernée.' })

type Valeurs = z.infer<typeof schema>

/** Date ISO → valeur d'un champ datetime-local, en heure locale. */
function versChampLocal(iso: string): string {
  const d = new Date(iso)
  const deux = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${deux(d.getMonth() + 1)}-${deux(d.getDate())}T${deux(d.getHours())}:${deux(d.getMinutes())}`
}

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  annonce: Annonce | null
}

export function AnnonceFormDialog({ open, onOpenChange, annonce }: Props) {
  const save = annonces.useSave()
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: open && !!anneeActive.data })
  const form = useForm<Valeurs>({ resolver: zodResolver(schema) })
  const { errors, isSubmitting } = form.formState
  const cible = useWatch({ control: form.control, name: 'cible' })

  useEffect(() => {
    if (!open) return
    form.reset(
      annonce
        ? {
            titre: annonce.titre,
            contenu: annonce.contenu,
            cible: annonce.cible,
            classe_id: annonce.classe?.id,
            date_publication: versChampLocal(annonce.date_publication),
          }
        : { titre: '', contenu: '', cible: 'tous', classe_id: undefined, date_publication: '' },
    )
  }, [open, annonce, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      await save.mutateAsync({
        id: annonce?.id,
        donnees: {
          titre: valeurs.titre,
          contenu: valeurs.contenu,
          cible: valeurs.cible,
          classe_id: valeurs.cible === 'classe' ? (valeurs.classe_id ?? null) : null,
          // Heure locale convertie en ISO (avec fuseau) pour l'API
          date_publication: valeurs.date_publication ? new Date(valeurs.date_publication).toISOString() : null,
        },
      })
      toast.success(annonce ? 'Annonce modifiée.' : 'Annonce publiée.')
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={annonce ? 'Modifier l’annonce' : 'Nouvelle annonce'}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
      submitLabel={annonce ? 'Enregistrer' : 'Publier'}
    >
      <Field label="Titre" htmlFor="annonce-titre" error={errors.titre?.message}>
        <Input id="annonce-titre" aria-invalid={!!errors.titre} {...form.register('titre')} />
      </Field>
      <Field label="Message" htmlFor="annonce-contenu" error={errors.contenu?.message}>
        <Textarea id="annonce-contenu" rows={6} aria-invalid={!!errors.contenu} {...form.register('contenu')} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Destinataires" htmlFor="annonce-cible" error={errors.cible?.message}>
          <Controller
            control={form.control}
            name="cible"
            render={({ field }) => (
              <Select value={field.value} onValueChange={(v) => field.onChange(v as CibleAnnonce)}>
                <SelectTrigger id="annonce-cible" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(CIBLES).map(([valeur, libelle]) => (
                    <SelectItem key={valeur} value={valeur}>
                      {libelle}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </Field>
        {cible === 'classe' && (
          <Field label="Classe" htmlFor="annonce-classe" error={errors.classe_id?.message} hint="Élèves, parents et professeurs de la classe.">
            <Controller
              control={form.control}
              name="classe_id"
              render={({ field }) => (
                <SelectField
                  id="annonce-classe"
                  value={field.value}
                  onChange={field.onChange}
                  invalid={!!errors.classe_id}
                  options={listeClasses.data?.data.map((c) => ({ value: c.id, label: c.nom })) ?? []}
                />
              )}
            />
          </Field>
        )}
      </div>
      <Field label="Publication" htmlFor="annonce-date" error={errors.date_publication?.message} hint="Laisser vide pour publier immédiatement ; une date future programme l’annonce.">
        <Input id="annonce-date" type="datetime-local" {...form.register('date_publication')} />
      </Field>
    </FormDialog>
  )
}
