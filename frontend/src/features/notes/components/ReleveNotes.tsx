import { useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatDate } from '@/lib/format'
import { useReleve } from '../api'
import { formatNote } from '../saisie'

/**
 * Notes d'un élève par matière pour une période (élève, parent, professeurs, administration).
 * Les moyennes officielles sont calculées sur le bulletin.
 */
export function ReleveNotes({ eleveId }: { eleveId: number }) {
  const [periodeChoisie, setPeriodeChoisie] = useState<number>()
  const releve = useReleve(eleveId, periodeChoisie)

  if (releve.isPending) return <Skeleton className="h-48 w-full" />
  if (releve.isError) return <p className="text-sm text-muted-foreground">Relevé indisponible.</p>

  const { periodes, periode_id, matieres } = releve.data

  if (periodes.length === 0) return <p className="text-sm text-muted-foreground">Aucune période pour cet élève.</p>

  return (
    <div className="space-y-4">
      <Tabs value={String(periode_id)} onValueChange={(v) => setPeriodeChoisie(Number(v))}>
        <TabsList>
          {periodes.map((p) => (
            <TabsTrigger key={p.id} value={String(p.id)}>
              {p.libelle}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {matieres.length === 0 && <p className="text-sm text-muted-foreground">Aucune matière pour cette période.</p>}

      <div className="divide-y rounded-lg border bg-background">
        {matieres.map(({ matiere, enseignant, evaluations }) => (
          <div key={matiere.id} className="grid gap-2 p-3 sm:grid-cols-[14rem_1fr]">
            <div>
              <p className="text-sm font-medium">{matiere.nom}</p>
              <p className="text-xs text-muted-foreground">{enseignant}</p>
            </div>
            {evaluations.length === 0 ? (
              <p className="self-center text-sm text-muted-foreground">Pas encore d’évaluation.</p>
            ) : (
              <ul className="flex flex-wrap gap-2">
                {evaluations.map((e) => (
                  <li
                    key={e.id}
                    title={`${e.titre} · ${e.type_libelle} du ${formatDate(e.date)}`}
                    className="rounded-md border px-2 py-1 text-sm"
                  >
                    <span className="block text-xs text-muted-foreground">
                      {e.titre}
                      {e.type === 'composition' && (
                        <Badge variant="secondary" className="ml-1 h-4 px-1 text-[10px]">
                          Compo
                        </Badge>
                      )}
                    </span>
                    <span className="font-medium tabular-nums">
                      {e.absent ? 'Absent' : e.valeur === null ? '—' : `${formatNote(e.valeur)} / ${formatNote(e.bareme)}`}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
