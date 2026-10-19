import { Wallet } from 'lucide-react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { toast } from 'sonner'
import { Field } from '@/components/form/Field'
import { FormDialog } from '@/components/form/FormDialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { aujourdhuiIso } from '@/lib/format'
import { handleFormError } from '@/lib/form-errors'
import { telechargerRecu, useEncaisser } from '../api'
import { deviseActive, formatMontant, lireMontant, montantEnSaisie } from '../format'
import { paiementSchema, type PaiementValues } from '../schemas'
import { MODES, type ModePaiement, type Situation } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  situation: Situation
}

/** Encaissement d'une tranche : montant libre (plafonné au reste), propositions retard / solde. */
export function EncaissementDialog({ open, onOpenChange, situation }: Props) {
  const encaisser = useEncaisser(situation.inscription_id)
  const form = useForm<PaiementValues>({ resolver: zodResolver(paiementSchema) })
  const { errors, isSubmitting } = form.formState
  const mode = useWatch({ control: form.control, name: 'mode' })

  useEffect(() => {
    if (open) {
      form.reset({
        montant: montantEnSaisie(situation.retard > 0 ? situation.retard : situation.reste),
        date: aujourdhuiIso(),
        mode: 'virement',
        reference: '',
      })
    }
  }, [open, situation.retard, situation.reste, form])

  const onSubmit = form.handleSubmit(async (valeurs) => {
    try {
      const paiement = await encaisser.mutateAsync({
        montant: lireMontant(valeurs.montant)!,
        date: valeurs.date,
        mode: valeurs.mode,
        reference: valeurs.mode === 'especes' ? null : valeurs.reference,
      })
      toast.success(`Paiement enregistré : reçu ${paiement.numero_recu}.`, {
        action: { label: 'Télécharger', onClick: () => void telechargerRecu(paiement.id) },
      })
      onOpenChange(false)
    } catch (error) {
      handleFormError(error, form.setError)
    }
  })

  const proposer = (montant: number) => form.setValue('montant', montantEnSaisie(montant), { shouldValidate: true })

  return (
    <FormDialog
      icone={Wallet}
      open={open}
      onOpenChange={onOpenChange}
      title="Encaisser un paiement"
      description={`${situation.eleve.prenom} ${situation.eleve.nom} · reste à payer ${formatMontant(situation.reste)}`}
      onSubmit={onSubmit}
      isSubmitting={isSubmitting}
      submitLabel="Encaisser"
    >
      <Field label={`Montant (${deviseActive().symbole})`} htmlFor="paiement-montant" error={errors.montant?.message}>
        <Input id="paiement-montant" inputMode="decimal" className="text-right tabular-nums" aria-invalid={!!errors.montant} {...form.register('montant')} />
      </Field>
      <div className="flex flex-wrap gap-2">
        {situation.retard > 0 && (
          <Button type="button" size="sm" variant="outline" onClick={() => proposer(situation.retard)}>
            Retard : {formatMontant(situation.retard)}
          </Button>
        )}
        <Button type="button" size="sm" variant="outline" onClick={() => proposer(situation.reste)}>
          Solde : {formatMontant(situation.reste)}
        </Button>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Date" htmlFor="paiement-date" error={errors.date?.message}>
          <Input id="paiement-date" type="date" max={aujourdhuiIso()} aria-invalid={!!errors.date} {...form.register('date')} />
        </Field>
        <Field label="Mode" htmlFor="paiement-mode" error={errors.mode?.message}>
          <Controller
            control={form.control}
            name="mode"
            render={({ field }) => (
              <Select value={field.value} onValueChange={(v) => field.onChange(v as ModePaiement)}>
                <SelectTrigger id="paiement-mode" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(MODES).map(([valeur, libelle]) => (
                    <SelectItem key={valeur} value={valeur}>
                      {libelle}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </Field>
      </div>
      {mode !== 'especes' && (
        <Field
          label="Référence"
          htmlFor="paiement-reference"
          error={errors.reference?.message}
          hint={mode === 'cheque' ? 'Numéro du chèque.' : mode === 'virement' ? 'Référence du virement.' : 'Numéro de transaction.'}
        >
          <Input id="paiement-reference" aria-invalid={!!errors.reference} {...form.register('reference')} />
        </Field>
      )}
    </FormDialog>
  )
}
