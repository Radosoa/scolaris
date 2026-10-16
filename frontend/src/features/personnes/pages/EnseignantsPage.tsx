import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-react'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/features/auth/auth-context'
import { useDialogState } from '@/hooks/use-dialog-state'
import { enseignants } from '../api'
import { EnseignantFormDialog } from '../components/EnseignantFormDialog'
import type { Enseignant } from '../types'

export function EnseignantsPage() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const peutGerer = user?.permissions.includes('enseignants.gerer') ?? false

  const liste = useListState(20)
  const requete = enseignants.useList(liste.params)
  const suppression = enseignants.useRemove()
  const formulaire = useDialogState<Enseignant>()
  const aSupprimer = useDialogState<Enseignant>()

  const colonnes: Column<Enseignant>[] = [
    {
      header: 'Enseignant',
      cell: (e) => (
        <span>
          <span className="block font-medium">
            {e.nom.toUpperCase()} {e.prenom}
          </span>
          <span className="block text-xs text-muted-foreground">{e.email}</span>
        </span>
      ),
    },
    { header: 'Spécialité', cell: (e) => e.specialite ?? '—', className: 'hidden sm:table-cell' },
    { header: 'Téléphone', cell: (e) => e.telephone ?? '—', className: 'hidden md:table-cell' },
    { header: 'Affectations', cell: (e) => e.affectations_count, className: 'text-right' },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (e) => (
        <RowActions
          label={e.nom_complet}
          actions={[
            { label: 'Voir ses affectations', icon: ClipboardList, onSelect: () => navigate(`/affectations?enseignant=${e.id}`) },
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(e), hidden: !peutGerer },
            { label: 'Archiver', icon: Trash2, onSelect: () => aSupprimer.ouvrir(e), destructive: true, hidden: !peutGerer },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Enseignants"
        description="Chaque enseignant dispose d’un compte pour saisir les notes et les absences de ses classes."
        actions={
          peutGerer && (
            <Button onClick={() => formulaire.ouvrir()}>
              <Plus />
              Nouvel enseignant
            </Button>
          )
        }
      />

      <div className="mb-4">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Nom ou spécialité…" />
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucun enseignant."
      />

      <EnseignantFormDialog open={formulaire.open} onOpenChange={formulaire.setOpen} enseignant={formulaire.item} />
      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Archiver ${aSupprimer.item?.nom_complet} ?`}
        description="Son compte de connexion sera désactivé. Impossible tant qu’il a des affectations sur l’année en cours."
        confirmLabel="Archiver"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Enseignant archivé.')
        }}
      />
    </>
  )
}
