import { Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useDialogState } from '@/hooks/use-dialog-state'
import { matieres } from '../api'
import { CoefficientsGrille } from '../components/CoefficientsGrille'
import { MatiereFormDialog } from '../components/MatiereFormDialog'
import type { Matiere } from '../types'

export function MatieresPage() {
  return (
    <>
      <PageHeader
        title="Matières & coefficients"
        description="Le coefficient d’une matière dépend du niveau. Une matière sans coefficient n’est pas enseignée à ce niveau."
      />
      <Tabs defaultValue="matieres">
        <TabsList className="mb-4">
          <TabsTrigger value="matieres">Matières</TabsTrigger>
          <TabsTrigger value="coefficients">Coefficients par niveau</TabsTrigger>
        </TabsList>
        <TabsContent value="matieres">
          <MatieresOnglet />
        </TabsContent>
        <TabsContent value="coefficients">
          <CoefficientsGrille />
        </TabsContent>
      </Tabs>
    </>
  )
}

function MatieresOnglet() {
  const liste = useListState()
  const requete = matieres.useList(liste.params)
  const suppression = matieres.useRemove()
  const formulaire = useDialogState<Matiere>()
  const aSupprimer = useDialogState<Matiere>()

  const colonnes: Column<Matiere>[] = [
    { header: 'Matière', cell: (m) => <span className="font-medium">{m.nom}</span> },
    { header: 'Code', cell: (m) => <Badge variant="outline">{m.code}</Badge> },
    {
      header: 'Enseignée en',
      cell: (m) => (m.coefficients_count ? `${m.coefficients_count} niveau(x)` : '—'),
      className: 'hidden sm:table-cell',
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (m) => (
        <RowActions
          label={m.nom}
          actions={[
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(m) },
            { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(m), destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Nom ou code…" />
        <Button className="sm:ml-auto" onClick={() => formulaire.ouvrir()}>
          <Plus />
          Nouvelle matière
        </Button>
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucune matière."
      />

      <MatiereFormDialog open={formulaire.open} onOpenChange={formulaire.setOpen} matiere={formulaire.item} />

      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer ${aSupprimer.item?.nom} ?`}
        description="Ses coefficients seront supprimés. Impossible si des enseignants y sont affectés."
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Matière supprimée.')
        }}
      />
    </>
  )
}
