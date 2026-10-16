import { Badge } from '@/components/ui/badge'
import { formatNombre } from '@/lib/format'
import type { Absence } from '../types'

/** « Absence · 2 h » ou « Retard · 15 min ». */
export function TypeAbsence({ absence }: { absence: Absence }) {
  return absence.type === 'retard' ? (
    <Badge variant="outline">Retard · {absence.minutes_retard} min</Badge>
  ) : (
    <Badge variant="outline">Absence · {formatNombre(absence.duree_heures)} h</Badge>
  )
}

/** Justifiée (avec le motif) ou à justifier. */
export function StatutAbsence({ absence }: { absence: Absence }) {
  return absence.justifiee ? (
    <span className="flex flex-wrap items-center gap-1.5">
      <Badge variant="secondary">Justifiée</Badge>
      {absence.motif && <span className="text-xs text-muted-foreground">{absence.motif}</span>}
    </span>
  ) : (
    <Badge variant="destructive">À justifier</Badge>
  )
}
