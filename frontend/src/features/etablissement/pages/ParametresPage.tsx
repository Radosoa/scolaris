import { Check, Coins, Info } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/PageHeader'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatMontant, type Devise } from '@/features/finances/format'
import { getErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { ErreurChargement } from '@/pages/ErrorPages'
import { useModifierParametres, useParametres } from '../api'

/** Réglages de l'établissement (direction). */
export function ParametresPage() {
  const requete = useParametres()

  return (
    <>
      <PageHeader title="Paramètres" description="Réglages de l’établissement, appliqués à tous les utilisateurs." />
      {requete.isError && <ErreurChargement error={requete.error} />}
      {requete.isPending && <Skeleton className="h-96 w-full" />}
      {requete.data && <ChoixDevise actuelle={requete.data.devise} devises={requete.data.devises} />}
    </>
  )
}

function ChoixDevise({ actuelle, devises }: { actuelle: Devise; devises: Devise[] }) {
  const modifier = useModifierParametres()
  const [choix, setChoix] = useState(actuelle.code)
  const devise = devises.find((d) => d.code === choix) ?? actuelle
  const modifie = choix !== actuelle.code

  const enregistrer = async () => {
    try {
      await modifier.mutateAsync({ devise: choix })
      toast.success(`Les montants s’affichent désormais en ${devise.libelle.toLowerCase()}.`)
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  return (
    <section className="grid gap-6 lg:grid-cols-[18rem_1fr]">
      <div className="space-y-2">
        <div className="flex size-10 items-center justify-center rounded-xl border bg-card text-muted-foreground shadow-xs">
          <Coins className="size-5" />
        </div>
        <h2 className="pt-2 font-semibold tracking-tight">Devise</h2>
        <p className="text-sm text-muted-foreground">
          Monnaie des frais de scolarité, des paiements, des reçus et des tableaux de bord.
        </p>
      </div>

      <div className="rounded-xl border bg-card shadow-xs">
        <div role="radiogroup" aria-label="Devise de l’établissement" className="grid gap-2 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-3">
          {devises.map((d) => {
            const selectionnee = d.code === choix
            return (
              <button
                key={d.code}
                type="button"
                role="radio"
                aria-checked={selectionnee}
                onClick={() => setChoix(d.code)}
                className={cn(
                  'flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                  selectionnee ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'hover:bg-muted/60',
                )}
              >
                <span
                  className={cn(
                    'flex h-8 min-w-12 items-center justify-center rounded-md px-1.5 font-mono text-xs font-semibold',
                    selectionnee ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                  )}
                >
                  {d.code}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-medium">{d.libelle}</span>
                  <span className="block text-xs text-muted-foreground">
                    {d.symbole} · {d.decimales ? `${d.decimales} décimales` : 'sans décimales'}
                  </span>
                </span>
                {selectionnee && <Check className="size-4 shrink-0 text-primary" />}
              </button>
            )
          })}
        </div>

        <div className="grid gap-4 border-t px-4 py-4 sm:grid-cols-2 sm:px-5">
          <div>
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Aperçu</p>
            <dl className="mt-2 space-y-1.5 text-sm">
              {[
                ['Frais annuels', devise.decimales ? 1250.5 : 1_250_000],
                ['Paiement reçu', devise.decimales ? 480 : 450_000],
              ].map(([libelle, montant]) => (
                <div key={libelle} className="flex items-baseline justify-between gap-4">
                  <dt className="text-muted-foreground">{libelle}</dt>
                  <dd className="font-medium tabular-nums">{formatMontant(Number(montant), devise)}</dd>
                </div>
              ))}
            </dl>
          </div>
          <p className="flex gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
            <Info className="mt-px size-4 shrink-0" />
            Les montants déjà saisis ne sont pas convertis : seule l’unité affichée change. Choisissez la devise avant de saisir les frais.
          </p>
        </div>

        <div className="flex justify-end gap-2 border-t bg-muted/30 px-4 py-3 sm:px-5">
          <Button variant="outline" disabled={!modifie || modifier.isPending} onClick={() => setChoix(actuelle.code)}>
            Annuler
          </Button>
          <Button disabled={!modifie || modifier.isPending} onClick={enregistrer}>
            Enregistrer
          </Button>
        </div>
      </div>
    </section>
  )
}
