import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { TriangleAlert, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { SelectField } from '@/components/form/SelectField'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { Affectation } from '@/features/personnes/types'
import { handleFormError } from '@/lib/form-errors'
import { useEnregistrerCreneau } from '../api'
import { formatHeure } from '../emploi-du-temps'
import { creneauSchema, type CreneauValues } from '../schemas'
import type { Creneau, Jour } from '../types'

const JOURS = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
]

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Créneau modifié, ou null pour une création */
  creneau: Creneau | null
  /** Jour et heure proposés pour une création (clic sur une case libre) */
  depart?: { jour: Jour; heure: number }
  /** Affectations proposées : celles de la classe ou du professeur affiché */
  affectations: Affectation[]
  /** Libellé d'une affectation selon la vue (« Maths — J. Moreau » ou « 6e A — Maths ») */
  libelle: (affectation: Affectation) => string
  onSupprimer?: (creneau: Creneau) => void
}

export function CreneauFormDialog({ open, onOpenChange, creneau, depart, affectations, libelle, onSupprimer }: Props) {
  const enregistrer = useEnregistrerCreneau()
  const form = useForm<CreneauValues>({ resolver: zodResolver(creneauSchema) })
  const { errors, isSubmitting } = form.formState
  const [conflits, setConflits] = useState<string[]>([])

  // Conflits effacés à chaque ouverture (ajustement pendant le rendu, pas dans l'effet)
  const [ouverturePrecedente, setOuverturePrecedente] = useState(open)
  if (open !== ouverturePrecedente) {
    setOuverturePrecedente(open)
    if (open) setConflits([])
  }

  useEffect(() => {
    if (!open) return
    form.reset(
      creneau
        ? { affectation_id: creneau.affectation_id, jour: creneau.jour, heure_debut: creneau.heure_debut, heure_fin: creneau.heure_fin, salle: creneau.salle ?? '' }
        : {
            affectation_id: undefined as unknown as number,
            jour: depart?.jour ?? 1,
            heure_debut: formatHeure(depart?.heure ?? 8),
            heure_fin: formatHeure((depart?.heure ?? 8) + 1),
            salle: '',
          },
    )
  }, [open, creneau, depart, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    setConflits([])
    try {
      await enregistrer.mutateAsync({ id: creneau?.id, donnees: { ...valeurs, salle: valeurs.salle || null } })
      toast.success(creneau ? 'Créneau modifié.' : 'Créneau ajouté.')
      onOpenChange(false)
    } catch (error) {
      // Conflits de professeur, de classe ou de salle : affichés en bloc au-dessus du formulaire
      const liste = axios.isAxiosError(error) ? error.response?.data?.errors?.conflits : undefined
      if (Array.isArray(liste)) {
        setConflits(liste)
        return
      }
      handleFormError(error, form.setError)
    }
  })

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={creneau ? 'Modifier le créneau' : 'Nouveau créneau'}
      description="Le professeur, la classe et la salle ne doivent pas déjà être occupés sur cette plage."
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
    >
      {conflits.length > 0 && (
        <Alert variant="destructive" role="alert">
          <TriangleAlert />
          <AlertTitle>Créneau impossible</AlertTitle>
          <AlertDescription>
            <ul className="list-disc space-y-1 pl-4">
              {conflits.map((conflit) => (
                <li key={conflit}>{conflit}</li>
              ))}
            </ul>
          </AlertDescription>
        </Alert>
      )}

      <Field label="Cours" htmlFor="creneau-affectation" error={errors.affectation_id?.message}>
        <Controller
          control={form.control}
          name="affectation_id"
          render={({ field }) => (
            <SelectField
              id="creneau-affectation"
              value={field.value}
              onChange={field.onChange}
              invalid={!!errors.affectation_id}
              options={affectations.map((a) => ({ value: a.id, label: libelle(a) }))}
            />
          )}
        />
      </Field>
      <div className="grid gap-4 sm:grid-cols-3">
        <Field label="Jour" htmlFor="creneau-jour" error={errors.jour?.message}>
          <Controller
            control={form.control}
            name="jour"
            render={({ field }) => <SelectField id="creneau-jour" value={field.value} onChange={field.onChange} options={JOURS} />}
          />
        </Field>
        <Field label="Début" htmlFor="creneau-debut" error={errors.heure_debut?.message}>
          <Input id="creneau-debut" type="time" step={300} aria-invalid={!!errors.heure_debut} {...form.register('heure_debut')} />
        </Field>
        <Field label="Fin" htmlFor="creneau-fin" error={errors.heure_fin?.message}>
          <Input id="creneau-fin" type="time" step={300} aria-invalid={!!errors.heure_fin} {...form.register('heure_fin')} />
        </Field>
      </div>
      <Field label="Salle" htmlFor="creneau-salle" error={errors.salle?.message} hint="Facultatif. Ex. Salle 101, Gymnase.">
        <Input id="creneau-salle" aria-invalid={!!errors.salle} {...form.register('salle')} />
      </Field>

      {creneau && onSupprimer && (
        <Button type="button" variant="ghost" size="sm" className="text-destructive" onClick={() => onSupprimer(creneau)}>
          <Trash2 />
          Supprimer ce créneau
        </Button>
      )}
    </FormDialog>
  )
}
