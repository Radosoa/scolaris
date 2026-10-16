import { Eye, FileUp, Pencil, Plus, SearchX, Trash2 } from 'lucide-react'
import { useState } from 'react'
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useAuth } from '@/features/auth/auth-context'
import { classes, useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { formatDate } from '@/lib/format'
import { eleves } from '../api'
import { EleveAvatar } from '../components/EleveAvatar'
import { EleveFormDialog } from '../components/EleveFormDialog'
import { ImportElevesDialog } from '../components/ImportElevesDialog'
import type { Eleve } from '../types'

const TOUTES = 'toutes'

export function ElevesPage() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const peutGerer = user?.permissions.includes('eleves.gerer') ?? false

  const liste = useListState(20)
  const [classeId, setClasseId] = useState(TOUTES)
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })
  const requete = eleves.useList({ ...liste.params, classe_id: classeId === TOUTES ? undefined : classeId })
  const suppression = eleves.useRemove()

  const formulaire = useDialogState<Eleve>()
  const aSupprimer = useDialogState<Eleve>()
  const [importOuvert, setImportOuvert] = useState(false)

  const colonnes: Column<Eleve>[] = [
    {
      header: 'Élève',
      cell: (eleve) => (
        <Link to={`/eleves/${eleve.id}`} className="flex items-center gap-3 hover:underline">
          <EleveAvatar eleve={eleve} />
          <span>
            <span className="block font-medium">
              {eleve.nom.toUpperCase()} {eleve.prenom}
            </span>
            <span className="block text-xs text-muted-foreground">{eleve.matricule}</span>
          </span>
        </Link>
      ),
    },
    {
      header: 'Classe',
      cell: (eleve) =>
        eleve.inscription_active ? (
          eleve.inscription_active.statut === 'inscrit' ? (
            eleve.inscription_active.classe?.nom
          ) : (
            <Badge variant="secondary">{eleve.inscription_active.statut_libelle}</Badge>
          )
        ) : (
          <span className="text-muted-foreground">Non inscrit</span>
        ),
    },
    { header: 'Sexe', cell: (eleve) => (eleve.sexe === 'F' ? 'F' : 'M'), className: 'hidden sm:table-cell w-16' },
    { header: 'Naissance', cell: (eleve) => formatDate(eleve.date_naissance), className: 'hidden md:table-cell' },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (eleve) => (
        <RowActions
          label={eleve.nom_complet}
          actions={[
            { label: 'Voir la fiche', icon: Eye, onSelect: () => navigate(`/eleves/${eleve.id}`) },
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(eleve), hidden: !peutGerer },
            { label: 'Archiver', icon: Trash2, onSelect: () => aSupprimer.ouvrir(eleve), destructive: true, hidden: !peutGerer },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Élèves"
        description={peutGerer ? 'Dossiers des élèves, parents et inscriptions.' : 'Élèves des classes où vous enseignez.'}
        actions={
          peutGerer && (
            <>
              <Button variant="outline" onClick={() => setImportOuvert(true)}>
                <FileUp />
                Importer
              </Button>
              <Button onClick={() => formulaire.ouvrir()}>
                <Plus />
                Nouvel élève
              </Button>
            </>
          )
        }
      />

      <div className="mb-4 flex flex-col gap-2 sm:flex-row">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Nom, prénom ou matricule…" />
        <Select
          value={classeId}
          onValueChange={(valeur) => {
            setClasseId(valeur)
            liste.setPage(1)
          }}
        >
          <SelectTrigger className="w-full sm:w-44" aria-label="Classe">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUTES}>Toutes les classes</SelectItem>
            {listeClasses.data?.data.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.nom}
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
        onPageChange={liste.setPage}
        emptyMessage="Aucun élève trouvé"
        emptyDescription="Vérifiez l’orthographe ou élargissez les filtres (classe, recherche)."
        emptyIcon={SearchX}
      />

      <EleveFormDialog
        open={formulaire.open}
        onOpenChange={formulaire.setOpen}
        eleve={formulaire.item}
        onSaved={(eleve) => !formulaire.item && navigate(`/eleves/${eleve.id}`)}
      />
      <ImportElevesDialog open={importOuvert} onOpenChange={setImportOuvert} />

      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Archiver le dossier de ${aSupprimer.item?.nom_complet} ?`}
        description="Le dossier est retiré des listes mais ses notes, bulletins et paiements sont conservés. Son compte de connexion est désactivé."
        confirmLabel="Archiver"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Dossier archivé.')
        }}
      />
    </>
  )
}
