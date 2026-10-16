import { ArrowLeft, Lock, LockOpen, Pencil, Plus, Trash2 } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useDialogState } from '@/hooks/use-dialog-state'
import { formatDate } from '@/lib/format'
import { ErreurChargement } from '@/pages/ErrorPages'
import { annees, periodes, useBasculerCloture } from '../api'
import { PeriodeFormDialog } from '../components/PeriodeFormDialog'
import type { Periode } from '../types'

export function AnneeDetailPage() {
  const id = Number(useParams().id)
  const annee = annees.useDetail(id)
  const listePeriodes = periodes.useList({ annee_scolaire_id: id, per_page: 100 })
  const suppression = periodes.useRemove()
  const cloture = useBasculerCloture()

  const formulaire = useDialogState<Periode>()
  const aSupprimer = useDialogState<Periode>()
  const aBasculer = useDialogState<Periode>()

  if (annee.isError) return <ErreurChargement error={annee.error} />

  const lignes = listePeriodes.data?.data ?? []
  const ordreSuivant = Math.max(0, ...lignes.map((p) => p.ordre)) + 1

  const colonnes: Column<Periode>[] = [
    { header: 'N°', cell: (p) => p.ordre, className: 'w-12' },
    { header: 'Période', cell: (p) => <span className="font-medium">{p.libelle}</span> },
    { header: 'Du', cell: (p) => formatDate(p.date_debut) },
    { header: 'Au', cell: (p) => formatDate(p.date_fin) },
    {
      header: 'État',
      cell: (p) =>
        p.cloturee ? (
          <Badge variant="secondary">
            <Lock />
            Clôturée
          </Badge>
        ) : (
          <Badge variant="outline">Ouverte</Badge>
        ),
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (p) => (
        <RowActions
          label={p.libelle}
          actions={[
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(p), hidden: p.cloturee },
            { label: 'Clôturer', icon: Lock, onSelect: () => aBasculer.ouvrir(p), hidden: p.cloturee },
            { label: 'Rouvrir', icon: LockOpen, onSelect: () => aBasculer.ouvrir(p), hidden: !p.cloturee },
            { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(p), destructive: true, hidden: p.cloturee },
          ]}
        />
      ),
    },
  ]

  const cible = aBasculer.item

  return (
    <>
      <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
        <Link to="/structure/annees">
          <ArrowLeft />
          Années scolaires
        </Link>
      </Button>

      {annee.data ? (
        <PageHeader
          title={`Année ${annee.data.libelle}`}
          description={`Du ${formatDate(annee.data.date_debut)} au ${formatDate(annee.data.date_fin)}${annee.data.active ? ' · année active' : ''}`}
          actions={
            <Button onClick={() => formulaire.ouvrir()}>
              <Plus />
              Nouvelle période
            </Button>
          }
        />
      ) : (
        <Skeleton className="mb-6 h-14 w-72" />
      )}

      <DataTable
        columns={colonnes}
        data={lignes}
        isLoading={listePeriodes.isPending}
        emptyMessage="Aucune période. Découpez l’année en trimestres ou en semestres."
      />

      <p className="mt-3 text-sm text-muted-foreground">
        Une période clôturée bloque la saisie des notes et permet de générer les bulletins.
      </p>

      {annee.data && (
        <PeriodeFormDialog
          open={formulaire.open}
          onOpenChange={formulaire.setOpen}
          annee={annee.data}
          periode={formulaire.item}
          ordreSuivant={ordreSuivant}
        />
      )}

      <ConfirmDialog
        open={aBasculer.open}
        onOpenChange={aBasculer.setOpen}
        title={cible?.cloturee ? `Rouvrir ${cible.libelle} ?` : `Clôturer ${cible?.libelle} ?`}
        description={
          cible?.cloturee
            ? 'Les professeurs pourront de nouveau saisir et modifier des notes sur cette période.'
            : 'La saisie des notes sera bloquée sur cette période et les bulletins pourront être générés.'
        }
        confirmLabel={cible?.cloturee ? 'Rouvrir' : 'Clôturer'}
        onConfirm={async () => {
          await cloture.mutateAsync({ id: cible!.id, cloturer: !cible!.cloturee })
          toast.success(cible!.cloturee ? 'Période rouverte.' : 'Période clôturée.')
        }}
      />

      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer ${aSupprimer.item?.libelle} ?`}
        description="Impossible si des évaluations y sont rattachées."
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Période supprimée.')
        }}
      />
    </>
  )
}
