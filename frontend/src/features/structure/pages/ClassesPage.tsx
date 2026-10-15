import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useDialogState } from '@/hooks/use-dialog-state'
import { annees, classes, niveaux, TOUS, useAnneeActive } from '../api'
import { ClasseFormDialog } from '../components/ClasseFormDialog'
import { NiveauFormDialog } from '../components/NiveauFormDialog'
import type { Classe, Niveau } from '../types'

export function ClassesPage() {
  return (
    <>
      <PageHeader title="Niveaux & classes" description="Les classes sont rattachées à un niveau et à une année scolaire." />
      <Tabs defaultValue="classes">
        <TabsList className="mb-4">
          <TabsTrigger value="classes">Classes</TabsTrigger>
          <TabsTrigger value="niveaux">Niveaux</TabsTrigger>
        </TabsList>
        <TabsContent value="classes">
          <ClassesOnglet />
        </TabsContent>
        <TabsContent value="niveaux">
          <NiveauxOnglet />
        </TabsContent>
      </Tabs>
    </>
  )
}

/** Barre de remplissage effectif / capacité. */
function Remplissage({ effectif = 0, capacite }: { effectif?: number; capacite: number }) {
  const taux = Math.min(100, Math.round((effectif / capacite) * 100))
  return (
    <div className="flex items-center gap-2">
      <div className="h-1.5 w-16 overflow-hidden rounded-full bg-muted" aria-hidden>
        <div className={taux >= 100 ? 'h-full bg-destructive' : 'h-full bg-primary'} style={{ width: `${taux}%` }} />
      </div>
      <span className="text-sm tabular-nums">
        {effectif} / {capacite}
      </span>
    </div>
  )
}

const TOUT = 'tous'

function ClassesOnglet() {
  const liste = useListState()
  const anneeActive = useAnneeActive()
  const listeAnnees = annees.useList(TOUS)
  const listeNiveaux = niveaux.useList(TOUS)
  // undefined tant que l'utilisateur n'a rien choisi : on utilise alors l'année active
  const [anneeChoisie, setAnneeChoisie] = useState<string>()
  const [niveauId, setNiveauId] = useState<string>(TOUT)

  const anneeId = anneeChoisie ?? (anneeActive.data ? String(anneeActive.data.id) : TOUT)
  const requete = classes.useList(
    {
      ...liste.params,
      annee_scolaire_id: anneeId === TOUT ? undefined : anneeId,
      niveau_id: niveauId === TOUT ? undefined : niveauId,
    },
    { enabled: !anneeActive.isPending },
  )
  const suppression = classes.useRemove()
  const formulaire = useDialogState<Classe>()
  const aSupprimer = useDialogState<Classe>()

  const filtrer = (setter: (valeur: string) => void) => (valeur: string) => {
    setter(valeur)
    liste.setPage(1)
  }

  const colonnes: Column<Classe>[] = [
    { header: 'Classe', cell: (c) => <span className="font-medium">{c.nom}</span> },
    { header: 'Niveau', cell: (c) => c.niveau?.libelle },
    { header: 'Année', cell: (c) => c.annee_scolaire?.libelle, className: 'hidden md:table-cell' },
    { header: 'Effectif', cell: (c) => <Remplissage effectif={c.effectif} capacite={c.capacite} /> },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (c) => (
        <RowActions
          label={c.nom}
          actions={[
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(c) },
            { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(c), destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Rechercher une classe…" />
        <Select value={anneeId} onValueChange={filtrer(setAnneeChoisie)}>
          <SelectTrigger className="w-full lg:w-48" aria-label="Année scolaire">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUT}>Toutes les années</SelectItem>
            {listeAnnees.data?.data.map((a) => (
              <SelectItem key={a.id} value={String(a.id)}>
                {a.libelle}
                {a.active && ' (active)'}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={niveauId} onValueChange={filtrer(setNiveauId)}>
          <SelectTrigger className="w-full lg:w-44" aria-label="Niveau">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUT}>Tous les niveaux</SelectItem>
            {listeNiveaux.data?.data.map((n) => (
              <SelectItem key={n.id} value={String(n.id)}>
                {n.libelle}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button className="lg:ml-auto" onClick={() => formulaire.ouvrir()}>
          <Plus />
          Nouvelle classe
        </Button>
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucune classe pour ces critères."
      />

      <ClasseFormDialog
        open={formulaire.open}
        onOpenChange={formulaire.setOpen}
        classe={formulaire.item}
        anneeParDefaut={anneeId === TOUT ? anneeActive.data?.id : Number(anneeId)}
      />

      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer la classe ${aSupprimer.item?.nom} ?`}
        description="Impossible si des élèves y sont inscrits ou si des enseignants y sont affectés."
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Classe supprimée.')
        }}
      />
    </>
  )
}

function NiveauxOnglet() {
  const liste = useListState()
  const requete = niveaux.useList(liste.params)
  const suppression = niveaux.useRemove()
  const formulaire = useDialogState<Niveau>()
  const aSupprimer = useDialogState<Niveau>()

  const ordreSuivant = Math.max(0, ...(requete.data?.data.map((n) => n.ordre) ?? [])) + 1

  const colonnes: Column<Niveau>[] = [
    { header: 'Ordre', cell: (n) => n.ordre, className: 'w-16' },
    { header: 'Niveau', cell: (n) => <span className="font-medium">{n.libelle}</span> },
    { header: 'Classes', cell: (n) => n.classes_count, className: 'text-right' },
    { header: 'Matières', cell: (n) => n.coefficients_count, className: 'text-right' },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (n) => (
        <RowActions
          label={n.libelle}
          actions={[
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(n) },
            { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(n), destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Rechercher un niveau…" />
        <Button className="sm:ml-auto" onClick={() => formulaire.ouvrir()}>
          <Plus />
          Nouveau niveau
        </Button>
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucun niveau."
      />

      <NiveauFormDialog
        open={formulaire.open}
        onOpenChange={formulaire.setOpen}
        niveau={formulaire.item}
        ordreSuivant={ordreSuivant}
      />

      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer le niveau ${aSupprimer.item?.libelle} ?`}
        description="Ses coefficients seront supprimés. Impossible si des classes y sont rattachées."
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Niveau supprimé.')
        }}
      />
    </>
  )
}
