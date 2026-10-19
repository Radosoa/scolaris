import { ArrowDownRight, ArrowRight, ArrowUpRight, Minus, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import type { Annonce } from '@/features/annonces/types'
import { cn } from '@/lib/utils'

export interface Tendance {
  libelle: string
  /** Ex. « vs semaine précédente » */
  contexte?: string
  ton: 'positif' | 'negatif' | 'neutre'
  sens: 'hausse' | 'baisse' | 'stable'
}

interface IndicateurProps {
  libelle: string
  valeur: string
  /** Unité ou complément discret à droite du chiffre (ex. « / 20 ») */
  suffixe?: string
  detail?: ReactNode
  icone: LucideIcon
  alerte?: boolean
  lien?: string
  tendance?: Tendance
  /** Visuel sous le chiffre : jauge, mini-courbe… */
  children?: ReactNode
}

const TONS = {
  positif: 'bg-emerald-500/10 text-[var(--viz-texte-bon)]',
  negatif: 'bg-red-500/10 text-red-700 dark:text-red-300',
  neutre: 'bg-muted text-muted-foreground',
}

/** Indicateur clé : libellé, chiffre, tendance éventuelle et visuel de contexte. */
export function Indicateur({ libelle, valeur, suffixe, detail, icone: Icone, alerte, lien, tendance, children }: IndicateurProps) {
  const IconeTendance = tendance?.sens === 'hausse' ? ArrowUpRight : tendance?.sens === 'baisse' ? ArrowDownRight : Minus

  const contenu = (
    <div
      className={cn(
        'group relative flex h-full flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs transition-all',
        lien && 'hover:border-indigo-200 hover:shadow-md hover:shadow-indigo-500/5 dark:hover:border-indigo-500/40',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <p className="text-[13px] font-medium text-muted-foreground">{libelle}</p>
        <span
          className={cn(
            'flex size-8 shrink-0 items-center justify-center rounded-lg border',
            alerte ? 'border-red-200 bg-red-50 text-red-600 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300' : 'bg-muted/50 text-muted-foreground',
          )}
        >
          <Icone className="size-4" />
        </span>
      </div>

      <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <p className={cn('text-[1.7rem] leading-none font-semibold tracking-tight', alerte && 'text-red-600 dark:text-red-400')}>
          {valeur}
          {suffixe && <span className="ml-1 text-sm font-normal text-muted-foreground">{suffixe}</span>}
        </p>
        {tendance && (
          <span className={cn('inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums', TONS[tendance.ton])}>
            <IconeTendance className="size-3.5" aria-hidden />
            {tendance.libelle}
          </span>
        )}
      </div>

      {children}

      {(detail || tendance?.contexte) && (
        <p className="mt-auto text-xs text-muted-foreground">
          {detail}
          {detail && tendance?.contexte && ' · '}
          {tendance?.contexte}
        </p>
      )}

      {lien && <ArrowRight aria-hidden className="absolute right-4 bottom-4 size-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />}
    </div>
  )

  return lien ? (
    <Link to={lien} className="rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
      {contenu}
    </Link>
  ) : (
    contenu
  )
}

/** Panneau titré accueillant un graphique ou une liste. */
export function Panneau({
  titre,
  description,
  action,
  children,
  className,
}: {
  titre: string
  description?: ReactNode
  action?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <section className={cn('flex flex-col rounded-xl border bg-card shadow-xs', className)}>
      <header className="flex items-start justify-between gap-4 px-5 pt-4 pb-3">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold">{titre}</h2>
          {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
        </div>
        {action && <div className="shrink-0">{action}</div>}
      </header>
      <div className="flex-1 px-5 pb-5">{children}</div>
    </section>
  )
}

/** Lien discret en tête de panneau (« Voir tout »). */
export function LienPanneau({ to, children }: { to: string; children: ReactNode }) {
  return (
    <Link to={to} className="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300">
      {children}
      <ArrowRight className="size-3" />
    </Link>
  )
}

/** Trois dernières annonces destinées à l'utilisateur. */
export function AnnoncesRecentes({ annonces }: { annonces: Annonce[] }) {
  return (
    <Panneau titre="Dernières annonces" action={<LienPanneau to="/annonces">Toutes</LienPanneau>}>
      {annonces.length === 0 ? (
        <p className="text-sm text-muted-foreground">Aucune annonce.</p>
      ) : (
        <ul className="divide-y">
          {annonces.map((a) => (
            <li key={a.id} className="py-3 first:pt-0 last:pb-0">
              <div className="flex items-center gap-2">
                <p className="truncate text-sm font-medium">{a.titre}</p>
                <Badge variant="secondary" className="h-5 shrink-0 px-1.5 text-[10px]">
                  {a.cible === 'classe' ? a.classe?.nom : a.cible_libelle}
                </Badge>
              </div>
              <p className="mt-0.5 text-xs text-muted-foreground">
                {new Date(a.date_publication).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} · {a.auteur}
              </p>
              <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{a.contenu}</p>
            </li>
          ))}
        </ul>
      )}
    </Panneau>
  )
}
