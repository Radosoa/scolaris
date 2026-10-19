import { useId } from 'react'
import { Area, AreaChart, ResponsiveContainer } from 'recharts'
import { cn } from '@/lib/utils'

/** Mini-courbe de tendance (2 px, aire à 10 %, point final), sans axes. */
export function Sparkline({ valeurs, couleur, libelle }: { valeurs: number[]; couleur: string; libelle: string }) {
  const id = useId()
  if (valeurs.length < 2) return null

  const donnees = valeurs.map((v, i) => ({ i, v }))

  return (
    <div className="h-10 w-full" role="img" aria-label={libelle}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={donnees} margin={{ top: 5, right: 5, bottom: 2, left: 5 }}>
          <defs>
            <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={couleur} stopOpacity={0.18} />
              <stop offset="100%" stopColor={couleur} stopOpacity={0} />
            </linearGradient>
          </defs>
          <Area
            type="monotone"
            dataKey="v"
            stroke={couleur}
            strokeWidth={2}
            fill={`url(#${id})`}
            isAnimationActive={false}
            dot={(props) =>
              props.index === donnees.length - 1 ? (
                <circle key="fin" cx={props.cx} cy={props.cy} r={4} fill={couleur} stroke="var(--card)" strokeWidth={2} />
              ) : (
                <g key={props.index} />
              )
            }
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  )
}

/** Jauge horizontale : part d'un total (0 à 100). */
export function Jauge({ pourcentage, couleur, libelle }: { pourcentage: number; couleur: string; libelle: string }) {
  const borne = Math.max(0, Math.min(100, pourcentage))
  return (
    <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted" role="meter" aria-valuenow={borne} aria-valuemin={0} aria-valuemax={100} aria-label={libelle}>
      <div className="h-full rounded-full transition-[width] duration-700" style={{ width: `${borne}%`, background: couleur }} />
    </div>
  )
}

export interface EntreeLegende {
  libelle: string
  couleur: string
  valeur?: string
  detail?: string
}

/** Légende : pastille de couleur + libellé en encre normale (jamais dans la couleur de la série). */
export function Legende({ entrees, className }: { entrees: EntreeLegende[]; className?: string }) {
  return (
    <ul className={cn('flex flex-wrap gap-x-5 gap-y-2 text-xs', className)}>
      {entrees.map((e) => (
        <li key={e.libelle} className="flex items-center gap-2">
          <span aria-hidden className="size-2.5 shrink-0 rounded-[3px]" style={{ background: e.couleur }} />
          <span className="text-muted-foreground">{e.libelle}</span>
          {e.valeur && <span className="font-medium text-foreground tabular-nums">{e.valeur}</span>}
          {e.detail && <span className="text-muted-foreground tabular-nums">{e.detail}</span>}
        </li>
      ))}
    </ul>
  )
}

interface ElementInfobulle {
  name?: string | number
  value?: number | string | (number | string)[]
  color?: string
  dataKey?: string | number
}

/**
 * Infobulle des graphiques Recharts : titre, puis une ligne par série
 * (pastille de couleur, libellé, valeur formatée).
 */
export function Infobulle({
  active,
  payload,
  label,
  formater = (v) => String(v),
  titre = (l) => String(l),
}: {
  active?: boolean
  payload?: ElementInfobulle[]
  label?: string | number
  formater?: (valeur: number, serie: string) => string
  titre?: (label: string | number) => string
}) {
  if (!active || !payload?.length || label === undefined) return null

  return (
    <div className="min-w-36 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-lg">
      <p className="mb-1.5 font-medium">{titre(label)}</p>
      <ul className="space-y-1">
        {payload.map((p) => (
          <li key={String(p.dataKey)} className="flex items-center gap-2">
            <span aria-hidden className="size-2 rounded-[2px]" style={{ background: p.color }} />
            <span className="text-muted-foreground">{p.name}</span>
            <span className="ml-auto pl-3 font-medium tabular-nums">{formater(Number(p.value), String(p.name))}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
