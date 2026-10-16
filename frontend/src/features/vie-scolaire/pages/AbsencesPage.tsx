import { CalendarX2, CircleCheck, CircleX } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { toast } from 'sonner'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAuth } from '@/features/auth/auth-context'
import { useMesEnfants } from '@/features/personnes/api'
import { classes, useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { useAbsences, useJustifier } from '../api'
import { AbsencesEleve } from '../components/AbsencesEleve'
import { StatutAbsence, TypeAbsence } from '../components/BadgesAbsence'
import { JustificationDialog } from '../components/JustificationDialog'
import type { Absence } from '../types'

const TOUTES = 'toutes'

/** Vie scolaire : suivi et justification ; parent : absences de ses enfants. */
export function AbsencesPage() {
  const { user } = useAuth()
  return user?.permissions.includes('absences.justifier') ? <SuiviAbsences /> : <AbsencesEnfants />
}

function SuiviAbsences() {
  const liste = useListState(20)
  const [statut, setStatut] = useState<'a_justifier' | 'justifiees' | typeof TOUTES>('a_justifier')
  const [type, setType] = useState(TOUTES)
  const [classeId, setClasseId] = useState(TOUTES)
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })
  const justification = useDialogState<Absence>()
  const retrait = useJustifier()

  const requete = useAbsences({
    ...liste.params,
    statut: statut === TOUTES ? undefined : statut,
    type: type === TOUTES ? undefined : type,
    classe_id: classeId === TOUTES ? undefined : classeId,
  })

  const filtrer = (appliquer: () => void) => {
    appliquer()
    liste.setPage(1)
  }

  const retirer = async (absence: Absence) => {
    try {
      await retrait.mutateAsync({ id: absence.id, justifiee: false, motif: null, toute_la_journee: false })
      toast.success('Justification retirée.')
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  const colonnes: Column<Absence>[] = [
    { header: 'Date', className: 'w-28', cell: (a) => formatDate(a.date) },
    {
      header: 'Élève',
      cell: (a) => (
        <Link to={`/eleves/${a.eleve?.id}`} className="font-medium hover:underline">
          {a.eleve?.nom} {a.eleve?.prenom}
        </Link>
      ),
    },
    { header: 'Classe', className: 'hidden sm:table-cell', cell: (a) => a.classe?.nom ?? '—' },
    {
      header: 'Cours',
      className: 'hidden lg:table-cell',
      cell: (a) => (
        <span className="text-sm">
          <span className="tabular-nums">
            {a.heure_debut} – {a.heure_fin}
          </span>
          {a.matiere && <span className="text-muted-foreground"> · {a.matiere}</span>}
        </span>
      ),
    },
    { header: 'Type', cell: (a) => <TypeAbsence absence={a} /> },
    { header: 'Statut', cell: (a) => <StatutAbsence absence={a} /> },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (a) => (
        <RowActions
          label={`${a.eleve?.prenom} ${a.eleve?.nom}`}
          actions={[
            { label: a.justifiee ? 'Modifier le motif' : 'Justifier', icon: CircleCheck, onSelect: () => justification.ouvrir(a) },
            { label: 'Retirer la justification', icon: CircleX, onSelect: () => retirer(a), hidden: !a.justifiee, destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Absences"
        description="Absences et retards relevés lors des appels ; justifiez-les à réception d’un motif."
        actions={
          requete.data && (
            <Badge variant={requete.data.meta.a_justifier ? 'destructive' : 'secondary'} className="h-8 px-3">
              {requete.data.meta.a_justifier} à justifier
            </Badge>
          )
        }
      />

      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <Tabs value={statut} onValueChange={(v) => filtrer(() => setStatut(v as typeof statut))}>
          <TabsList>
            <TabsTrigger value="a_justifier">À justifier</TabsTrigger>
            <TabsTrigger value="justifiees">Justifiées</TabsTrigger>
            <TabsTrigger value={TOUTES}>Toutes</TabsTrigger>
          </TabsList>
        </Tabs>
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Élève ou matricule…" />
        <Select value={classeId} onValueChange={(v) => filtrer(() => setClasseId(v))}>
          <SelectTrigger className="w-full sm:w-40" aria-label="Classe">
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
        <Select value={type} onValueChange={(v) => filtrer(() => setType(v))}>
          <SelectTrigger className="w-full sm:w-40" aria-label="Type">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUTES}>Absences et retards</SelectItem>
            <SelectItem value="absence">Absences</SelectItem>
            <SelectItem value="retard">Retards</SelectItem>
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
        emptyMessage={statut === 'a_justifier' ? 'Aucune absence à justifier' : 'Aucune absence'}
        emptyDescription={statut === 'a_justifier' ? 'Toutes les absences relevées lors des appels ont été traitées.' : 'Aucune absence ne correspond à ces critères.'}
        emptyIcon={statut === 'a_justifier' ? CircleCheck : CalendarX2}
      />

      <JustificationDialog open={justification.open} onOpenChange={justification.setOpen} absence={justification.item} />
    </>
  )
}

function AbsencesEnfants() {
  const enfants = useMesEnfants()

  return (
    <>
      <PageHeader title="Absences" description="Les absences et retards de vos enfants. Pour justifier une absence, contactez la vie scolaire." />
      {enfants.isPending && <Skeleton className="h-40 w-full" />}
      {enfants.data?.length === 0 && <p className="text-sm text-muted-foreground">Aucun enfant n’est rattaché à votre compte.</p>}
      <div className="space-y-4">
        {enfants.data?.map((enfant) => (
          <Card key={enfant.id}>
            <CardHeader>
              <CardTitle>{enfant.nom_complet}</CardTitle>
            </CardHeader>
            <CardContent>
              <AbsencesEleve eleveId={enfant.id} />
            </CardContent>
          </Card>
        ))}
      </div>
    </>
  )
}
