import { useState } from 'react'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { Card, CardDescription, CardHeader } from '@/components/ui/card'
import { formatDate, formatNombre } from '@/lib/format'
import { useAbsencesEleve } from '../api'
import type { Absence } from '../types'
import { StatutAbsence, TypeAbsence } from './BadgesAbsence'

/** Absences et retards d'un élève avec ses totaux (parent, élève, fiche élève). */
export function AbsencesEleve({ eleveId }: { eleveId: number }) {
  const [page, setPage] = useState(1)
  const requete = useAbsencesEleve(eleveId, page)
  const totaux = requete.data?.totaux

  const colonnes: Column<Absence>[] = [
    { header: 'Date', className: 'w-28', cell: (a) => formatDate(a.date) },
    {
      header: 'Cours',
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
  ]

  return (
    <div className="space-y-3">
      {totaux && (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          <Total libelle="Heures justifiées" valeur={`${formatNombre(totaux.heures_justifiees)} h`} />
          <Total libelle="Heures non justifiées" valeur={`${formatNombre(totaux.heures_non_justifiees)} h`} />
          <Total libelle="Retards" valeur={String(totaux.retards)} />
          <Total libelle="À justifier" valeur={String(totaux.a_justifier)} />
        </div>
      )}
      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={setPage}
        emptyMessage="Aucune absence ni aucun retard."
      />
    </div>
  )
}

function Total({ libelle, valeur }: { libelle: string; valeur: string }) {
  return (
    <Card size="sm">
      <CardHeader>
        <CardDescription>{libelle}</CardDescription>
        <p className="text-xl font-semibold tabular-nums">{valeur}</p>
      </CardHeader>
    </Card>
  )
}
