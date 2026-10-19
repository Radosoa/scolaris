import axios from 'axios'
import { Plus, Trash2, CalendarClock } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { FormDialog } from '@/components/form/FormDialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { getErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { useEnregistrerEcheances } from '../api'
import { totalPourcentages } from '../format'
import type { Echeance } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  annee: { id: number; libelle: string; date_debut: string; date_fin: string }
  echeances: Echeance[]
}

type Ligne = { libelle: string; date_limite: string; pourcentage: string }

/** Échéancier de l'année : tranches (libellé, date limite, part en %) totalisant 100 %. */
export function EcheancierDialog({ open, onOpenChange, annee, echeances }: Props) {
  const enregistrer = useEnregistrerEcheances(annee.id)
  const [lignes, setLignes] = useState<Ligne[]>([])
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  // Lignes rechargées à chaque ouverture (ajustement pendant le rendu)
  const [ouverturePrecedente, setOuverturePrecedente] = useState(false)
  if (open !== ouverturePrecedente) {
    setOuverturePrecedente(open)
    if (open) {
      setLignes(echeances.map((e) => ({ libelle: e.libelle, date_limite: e.date_limite, pourcentage: String(e.pourcentage).replace('.', ',') })))
      setErreurs({})
    }
  }

  const valeurs = lignes.map((l) => ({ ...l, pourcentage: Number(l.pourcentage.replace(',', '.')) }))
  const total = totalPourcentages(valeurs)
  const modifier = (index: number, changement: Partial<Ligne>) =>
    setLignes((actuelles) => actuelles.map((l, i) => (i === index ? { ...l, ...changement } : l)))

  return (
    <FormDialog
      icone={CalendarClock}
      open={open}
      onOpenChange={onOpenChange}
      title={`Échéancier ${annee.libelle}`}
      description="Part cumulée des frais exigible à chaque date limite. Au-delà, le montant non réglé apparaît en retard."
      isSubmitting={enregistrer.isPending}
      onSubmit={async (e) => {
        e.preventDefault()
        try {
          await enregistrer.mutateAsync(valeurs)
          toast.success('Échéancier enregistré.')
          onOpenChange(false)
        } catch (error) {
          const detail = axios.isAxiosError(error) && error.response?.status === 422 ? error.response.data?.errors : null
          if (detail) {
            setErreurs(Object.fromEntries(Object.entries(detail as Record<string, string[]>).map(([cle, messages]) => [cle, messages[0]])))
          } else {
            toast.error(getErrorMessage(error))
          }
        }
      }}
    >
      <div className="space-y-2">
        {lignes.map((ligne, i) => (
          <div key={i} className="grid grid-cols-[1fr_9.5rem_5.5rem_auto] items-start gap-2">
            <Input aria-label={`Libellé de la tranche ${i + 1}`} value={ligne.libelle} onChange={(e) => modifier(i, { libelle: e.target.value })} />
            <div>
              <Input
                type="date"
                aria-label={`Date limite de la tranche ${i + 1}`}
                min={annee.date_debut}
                max={annee.date_fin}
                value={ligne.date_limite}
                aria-invalid={!!erreurs[`echeances.${i}.date_limite`]}
                onChange={(e) => modifier(i, { date_limite: e.target.value })}
              />
              {erreurs[`echeances.${i}.date_limite`] && <p className="mt-1 text-xs text-destructive">{erreurs[`echeances.${i}.date_limite`]}</p>}
            </div>
            <div className="relative">
              <Input
                inputMode="decimal"
                aria-label={`Part de la tranche ${i + 1} en pourcentage`}
                className="pr-6 text-right"
                value={ligne.pourcentage}
                onChange={(e) => modifier(i, { pourcentage: e.target.value })}
              />
              <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-xs text-muted-foreground">%</span>
            </div>
            <Button type="button" variant="ghost" size="icon" aria-label={`Supprimer la tranche ${i + 1}`} onClick={() => setLignes((l) => l.filter((_, j) => j !== i))}>
              <Trash2 />
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setLignes((l) => [...l, { libelle: `Tranche ${l.length + 1}`, date_limite: annee.date_debut, pourcentage: String(Math.max(0, 100 - total)).replace('.', ',') }])}
        >
          <Plus />
          Ajouter une tranche
        </Button>
      </div>
      <p className={cn('text-sm', total === 100 || lignes.length === 0 ? 'text-muted-foreground' : 'text-destructive')}>
        Total : {total.toLocaleString('fr-FR')} %{total !== 100 && lignes.length > 0 && ' — les tranches doivent totaliser 100 %'}
      </p>
      {erreurs.echeances && <p className="text-sm text-destructive">{erreurs.echeances}</p>}
    </FormDialog>
  )
}
