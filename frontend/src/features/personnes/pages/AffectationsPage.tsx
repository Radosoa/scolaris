import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { classes, useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { affectations, enseignants } from '../api'
import { AffectationFormDialog } from '../components/AffectationFormDialog'
import type { Affectation } from '../types'

const TOUS = 'tous'

export function AffectationsPage() {
  // Filtres dans l'URL : la page peut être ouverte depuis la liste des enseignants
  const [params, setParams] = useSearchParams()
  const classeId = params.get('classe') ?? TOUS
  const enseignantId = params.get('enseignant') ?? TOUS
  const page = Number(params.get('page') ?? 1)

  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })
  const listeEnseignants = enseignants.useList({ per_page: 100 })
  const requete = affectations.useList({
    page,
    per_page: 25,
    classe_id: classeId === TOUS ? undefined : classeId,
    enseignant_id: enseignantId === TOUS ? undefined : enseignantId,
  })
  const suppression = affectations.useRemove()
  const formulaire = useDialogState<Affectation>()
  const aSupprimer = useDialogState<Affectation>()

  const filtrer = (cle: string, valeur: string) => {
    const suivants = new URLSearchParams(params)
    if (valeur === TOUS) suivants.delete(cle)
    else suivants.set(cle, valeur)
    suivants.delete('page')
    setParams(suivants, { replace: true })
  }

  const colonnes: Column<Affectation>[] = [
    { header: 'Classe', cell: (a) => <span className="font-medium">{a.classe?.nom}</span>, className: 'w-24' },
    {
      header: 'Matière',
      cell: (a) => (
        <span className="flex items-center gap-2">
          {a.matiere?.nom}
          <Badge variant="outline" className="hidden sm:inline-flex">
            {a.matiere?.code}
          </Badge>
        </span>
      ),
    },
    { header: 'Enseignant', cell: (a) => a.enseignant?.nom_complet },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (a) => (
        <RowActions
          label={`${a.matiere?.nom} en ${a.classe?.nom}`}
          actions={[
            { label: 'Changer d’enseignant', icon: Pencil, onSelect: () => formulaire.ouvrir(a) },
            { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(a), destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Affectations"
        description={`Qui enseigne quoi, dans quelle classe${anneeActive.data ? ` · année ${anneeActive.data.libelle}` : ''}.`}
        actions={
          <Button onClick={() => formulaire.ouvrir()}>
            <Plus />
            Nouvelle affectation
          </Button>
        }
      />

      <div className="mb-4 flex flex-col gap-2 sm:flex-row">
        <Select value={classeId} onValueChange={(valeur) => filtrer('classe', valeur)}>
          <SelectTrigger className="w-full sm:w-44" aria-label="Classe">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Toutes les classes</SelectItem>
            {listeClasses.data?.data.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.nom}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={enseignantId} onValueChange={(valeur) => filtrer('enseignant', valeur)}>
          <SelectTrigger className="w-full sm:w-64" aria-label="Enseignant">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Tous les enseignants</SelectItem>
            {listeEnseignants.data?.data.map((e) => (
              <SelectItem key={e.id} value={String(e.id)}>
                {e.nom_complet}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={(suivante) => {
          const suivants = new URLSearchParams(params)
          suivants.set('page', String(suivante))
          setParams(suivants, { replace: true })
        }}
        emptyMessage="Aucune affectation pour ces critères."
      />

      <AffectationFormDialog
        open={formulaire.open}
        onOpenChange={formulaire.setOpen}
        affectation={formulaire.item}
        classeParDefaut={classeId === TOUS ? undefined : Number(classeId)}
      />
      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title="Supprimer cette affectation ?"
        description={`${aSupprimer.item?.matiere?.nom} en ${aSupprimer.item?.classe?.nom}. Impossible si des évaluations ont déjà été créées.`}
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Affectation supprimée.')
        }}
      />
    </>
  )
}
