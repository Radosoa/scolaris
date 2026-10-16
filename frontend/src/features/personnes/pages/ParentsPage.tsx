import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react'
import { Link } from 'react-router'
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
import { tuteurs } from '../api'
import { CompteDialog } from '../components/CompteDialog'
import { TuteurFormDialog } from '../components/TuteurFormDialog'
import type { Tuteur } from '../types'

export function ParentsPage() {
  const liste = useListState(20)
  const requete = tuteurs.useList(liste.params)
  const suppression = tuteurs.useRemove()
  const formulaire = useDialogState<Tuteur>()
  const aSupprimer = useDialogState<Tuteur>()
  const compte = useDialogState<Tuteur>()

  const colonnes: Column<Tuteur>[] = [
    {
      header: 'Parent',
      cell: (t) => (
        <span>
          <span className="block font-medium">
            {t.nom.toUpperCase()} {t.prenom}
          </span>
          {t.email && <span className="block text-xs text-muted-foreground">{t.email}</span>}
        </span>
      ),
    },
    { header: 'Téléphone', cell: (t) => t.telephone, className: 'hidden sm:table-cell' },
    {
      header: 'Enfants',
      cell: (t) =>
        t.eleves?.length ? (
          <div className="flex flex-wrap gap-1">
            {t.eleves.map((e) => (
              <Link key={e.id} to={`/eleves/${e.id}`} className="text-sm hover:underline">
                {e.nom_complet}
                <span className="text-xs text-muted-foreground"> ({e.lien_libelle.toLowerCase()})</span>
              </Link>
            ))}
          </div>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    {
      header: 'Compte',
      cell: (t) => (t.compte ? <Badge variant="secondary">Actif</Badge> : <span className="text-muted-foreground">—</span>),
      className: 'hidden md:table-cell',
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (t) => (
        <RowActions
          label={t.nom_complet}
          actions={[
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(t) },
            { label: 'Créer le compte', icon: KeyRound, onSelect: () => compte.ouvrir(t), hidden: !!t.compte },
            { label: 'Archiver', icon: Trash2, onSelect: () => aSupprimer.ouvrir(t), destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Parents"
        description="Parents et responsables légaux. Les liens avec les enfants se gèrent depuis la fiche de l’élève."
        actions={
          <Button onClick={() => formulaire.ouvrir()}>
            <Plus />
            Nouveau parent
          </Button>
        }
      />

      <div className="mb-4">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Nom, téléphone ou e-mail…" />
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucun parent trouvé."
      />

      <TuteurFormDialog open={formulaire.open} onOpenChange={formulaire.setOpen} tuteur={formulaire.item} />
      {compte.item && (
        <CompteDialog
          open={compte.open}
          onOpenChange={compte.setOpen}
          type="tuteurs"
          id={compte.item.id}
          nom={compte.item.nom_complet}
          emailSuggere={compte.item.email}
        />
      )}
      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Archiver ${aSupprimer.item?.nom_complet} ?`}
        description="Le parent est retiré des listes et son compte de connexion est désactivé."
        confirmLabel="Archiver"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Parent archivé.')
        }}
      />
    </>
  )
}
