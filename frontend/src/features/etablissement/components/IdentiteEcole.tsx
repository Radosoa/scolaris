import { Building2 } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { applyValidationErrors, getErrorMessage } from '@/lib/api'
import { useModifierEcole, type IdentiteEcole as Identite } from '../api'

type Valeurs = Omit<Identite, 'code'>

const CHAMPS: { nom: keyof Valeurs; libelle: string; exemple: string; type?: string; large?: boolean }[] = [
  { nom: 'nom', libelle: 'Nom de l’établissement', exemple: 'Lycée Andohalo', large: true },
  { nom: 'adresse', libelle: 'Adresse', exemple: 'Lot II A 45, Andohalo', large: true },
  { nom: 'ville', libelle: 'Ville', exemple: 'Antananarivo' },
  { nom: 'telephone', libelle: 'Téléphone', exemple: '+261 34 00 000 00', type: 'tel' },
  { nom: 'email', libelle: 'E-mail', exemple: 'secretariat@lycee-andohalo.mg', type: 'email' },
  { nom: 'chef_etablissement', libelle: 'Chef d’établissement', exemple: 'Le Proviseur' },
]

/** Identité de l'école : en-tête des bulletins et des reçus, nom affiché dans le menu. */
export function IdentiteEcole({ ecole }: { ecole: Identite }) {
  const modifier = useModifierEcole()
  const form = useForm<Valeurs>({
    defaultValues: Object.fromEntries(CHAMPS.map(({ nom }) => [nom, ecole[nom] ?? ''])) as Valeurs,
  })
  const { errors, isDirty, isSubmitting } = form.formState

  const enregistrer = form.handleSubmit(async (valeurs) => {
    try {
      // Champs vides envoyés à null : l'en-tête des documents les omet
      const identite = Object.fromEntries(Object.entries(valeurs).map(([cle, v]) => [cle, v?.trim() ? v.trim() : null])) as Valeurs
      const enregistree = await modifier.mutateAsync(identite)
      form.reset(Object.fromEntries(CHAMPS.map(({ nom }) => [nom, enregistree[nom] ?? ''])) as Valeurs)
      toast.success('Informations de l’établissement enregistrées.')
    } catch (error) {
      if (!applyValidationErrors(error, form.setError)) toast.error(getErrorMessage(error))
    }
  })

  return (
    <section className="grid gap-6 lg:grid-cols-[18rem_1fr]">
      <div className="space-y-2">
        <div className="flex size-10 items-center justify-center rounded-xl border bg-card text-muted-foreground shadow-xs">
          <Building2 className="size-5" />
        </div>
        <h2 className="pt-2 font-semibold tracking-tight">Établissement</h2>
        <p className="text-sm text-muted-foreground">Nom et coordonnées imprimés en tête des bulletins et des reçus.</p>
        {ecole.code && (
          <p className="text-xs text-muted-foreground">
            Code de connexion : <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-foreground">{ecole.code}</span>
          </p>
        )}
      </div>

      <form onSubmit={enregistrer} noValidate className="rounded-xl border bg-card shadow-xs dark:shadow-none">
        <div className="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
          {CHAMPS.map(({ nom, libelle, exemple, type, large }) => (
            <div key={nom} className={large ? 'sm:col-span-2' : undefined}>
              <Field label={libelle} htmlFor={`ecole-${nom}`} error={errors[nom]?.message}>
                <Input
                  id={`ecole-${nom}`}
                  type={type}
                  placeholder={exemple}
                  aria-invalid={!!errors[nom]}
                  {...form.register(nom, nom === 'nom' ? { validate: (v) => !!v?.trim() || 'Le nom de l’établissement est obligatoire.' } : {})}
                />
              </Field>
            </div>
          ))}
        </div>
        <div className="flex justify-end gap-2 border-t px-4 py-3 sm:px-5">
          <Button type="button" variant="outline" disabled={!isDirty || isSubmitting} onClick={() => form.reset()}>
            Annuler
          </Button>
          <Button type="submit" disabled={!isDirty || isSubmitting}>
            Enregistrer
          </Button>
        </div>
      </form>
    </section>
  )
}
