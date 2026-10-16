import { Eye, Pencil } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { classes, useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { formatDate } from '@/lib/format'
import { inscriptions } from '../api'
import { InscriptionDialog } from '../components/InscriptionDialog'
import { STATUTS, type Inscription } from '../types'

const TOUS = 'tous'

export function InscriptionsPage() {
  const navigate = useNavigate()
  const liste = useListState(25)
  const [classeId, setClasseId] = useState(TOUS)
  const [statut, setStatut] = useState(TOUS)
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })
  const requete = inscriptions.useList({
    ...liste.params,
    classe_id: classeId === TOUS ? undefined : classeId,
    statut: statut === TOUS ? undefined : statut,
  })
  const edition = useDialogState<Inscription>()

  const filtrer = (setter: (valeur: string) => void) => (valeur: string) => {
    setter(valeur)
    liste.setPage(1)
  }

  const colonnes: Column<Inscription>[] = [
    {
      header: 'Élève',
      cell: (i) => (
        <Link to={`/eleves/${i.eleve_id}`} className="hover:underline">
          <span className="block font-medium">{i.eleve?.nom_complet}</span>
          <span className="block text-xs text-muted-foreground">{i.eleve?.matricule}</span>
        </Link>
      ),
    },
    { header: 'Classe', cell: (i) => i.classe?.nom },
    {
      header: 'Statut',
      cell: (i) => <Badge variant={i.statut === 'inscrit' ? 'default' : 'secondary'}>{i.statut_libelle}</Badge>,
    },
    { header: 'Depuis le', cell: (i) => formatDate(i.date_inscription), className: 'hidden md:table-cell' },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (i) => (
        <RowActions
          label={i.eleve?.nom_complet ?? ''}
          actions={[
            { label: 'Voir la fiche', icon: Eye, onSelect: () => navigate(`/eleves/${i.eleve_id}`) },
            { label: 'Changer de classe ou de statut', icon: Pencil, onSelect: () => edition.ouvrir(i) },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Inscriptions"
        description={
          anneeActive.data
            ? `Année ${anneeActive.data.libelle}. Pour inscrire un élève, ouvrez sa fiche.`
            : 'Aucune année scolaire active.'
        }
      />

      <div className="mb-4 flex flex-col gap-2 lg:flex-row">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Rechercher un élève…" />
        <Select value={classeId} onValueChange={filtrer(setClasseId)}>
          <SelectTrigger className="w-full lg:w-44" aria-label="Classe">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Toutes les classes</SelectItem>
            {listeClasses.data?.data.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.nom} ({c.effectif}/{c.capacite})
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={statut} onValueChange={filtrer(setStatut)}>
          <SelectTrigger className="w-full lg:w-40" aria-label="Statut">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Tous les statuts</SelectItem>
            {Object.entries(STATUTS).map(([valeur, libelle]) => (
              <SelectItem key={valeur} value={valeur}>
                {libelle}
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
        emptyMessage="Aucune inscription pour ces critères."
      />

      {edition.item && (
        <InscriptionDialog
          open={edition.open}
          onOpenChange={edition.setOpen}
          eleveId={edition.item.eleve_id}
          nomEleve={edition.item.eleve?.nom_complet ?? ''}
          inscription={edition.item}
        />
      )}
    </>
  )
}
