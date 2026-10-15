import { CalendarCheck, CalendarRange, Pencil, Plus, Trash2 } from 'lucide-react'
import { Link, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useDialogState } from '@/hooks/use-dialog-state'
import { formatDate } from '@/lib/format'
import { annees, useActiverAnnee } from '../api'
import { AnneeFormDialog } from '../components/AnneeFormDialog'
import type { AnneeScolaire } from '../types'

export function AnneesPage() {
  const navigate = useNavigate()
  const liste = useListState()
  const requete = annees.useList(liste.params)
  const suppression = annees.useRemove()
  const activation = useActiverAnnee()

  const formulaire = useDialogState<AnneeScolaire>()
  const aSupprimer = useDialogState<AnneeScolaire>()
  const aActiver = useDialogState<AnneeScolaire>()

  const colonnes: Column<AnneeScolaire>[] = [
    {
      header: 'Année',
      cell: (annee) => (
        <div className="flex items-center gap-2">
          <Link to={`/structure/annees/${annee.id}`} className="font-medium hover:underline">
            {annee.libelle}
          </Link>
          {annee.active && <Badge>Active</Badge>}
        </div>
      ),
    },
    {
      header: 'Dates',
      cell: (annee) => `${formatDate(annee.date_debut)} → ${formatDate(annee.date_fin)}`,
      className: 'hidden md:table-cell',
    },
    { header: 'Périodes', cell: (annee) => annee.periodes_count, className: 'text-right' },
    { header: 'Classes', cell: (annee) => annee.classes_count, className: 'hidden text-right sm:table-cell' },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (annee) => (
        <RowActions
          label={annee.libelle}
          actions={[
            { label: 'Gérer les périodes', icon: CalendarRange, onSelect: () => navigate(`/structure/annees/${annee.id}`) },
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(annee) },
            { label: 'Activer', icon: CalendarCheck, onSelect: () => aActiver.ouvrir(annee), hidden: annee.active },
            {
              label: 'Supprimer',
              icon: Trash2,
              onSelect: () => aSupprimer.ouvrir(annee),
              destructive: true,
              hidden: annee.active,
            },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Années scolaires"
        description="Une seule année est active à la fois : c’est elle qui sert aux inscriptions et à la saisie des notes."
        actions={
          <Button onClick={() => formulaire.ouvrir()}>
            <Plus />
            Nouvelle année
          </Button>
        }
      />

      <div className="mb-4">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Rechercher une année…" />
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucune année scolaire. Commencez par en créer une."
      />

      <AnneeFormDialog open={formulaire.open} onOpenChange={formulaire.setOpen} annee={formulaire.item} />

      <ConfirmDialog
        open={aActiver.open}
        onOpenChange={aActiver.setOpen}
        title={`Activer l’année ${aActiver.item?.libelle} ?`}
        description="L’année actuellement active sera désactivée. Les inscriptions et la saisie des notes porteront sur cette année."
        confirmLabel="Activer"
        onConfirm={async () => {
          await activation.mutateAsync(aActiver.item!.id)
          toast.success(`L’année ${aActiver.item!.libelle} est maintenant active.`)
        }}
      />

      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer l’année ${aSupprimer.item?.libelle} ?`}
        description="Ses périodes seront aussi supprimées. Cette action est irréversible."
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Année scolaire supprimée.')
        }}
      />
    </>
  )
}
