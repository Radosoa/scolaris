import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { Annonce } from '@/features/annonces/types'
import { cn } from '@/lib/utils'

interface IndicateurProps {
  libelle: string
  valeur: string
  detail?: string
  icone: LucideIcon
  alerte?: boolean
  lien?: string
}

/** Chiffre clé, éventuellement cliquable vers l'écran détaillé. */
export function Indicateur({ libelle, valeur, detail, icone: Icone, alerte, lien }: IndicateurProps) {
  const contenu = (
    <Card size="sm" className={cn('h-full', lien && 'transition-colors hover:bg-accent/40')}>
      <CardHeader>
        <CardDescription className="flex items-center gap-2">
          <Icone className={cn('size-4', alerte ? 'text-destructive' : 'text-primary')} />
          {libelle}
        </CardDescription>
        <p className={cn('text-2xl font-semibold tabular-nums', alerte && 'text-destructive')}>{valeur}</p>
        {detail && <p className="text-xs text-muted-foreground">{detail}</p>}
      </CardHeader>
    </Card>
  )

  return lien ? (
    <Link to={lien} className="rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
      {contenu}
    </Link>
  ) : (
    contenu
  )
}

/** Carte titrée accueillant un graphique ou une liste. */
export function Panneau({ titre, description, action, children, className }: { titre: string; description?: string; action?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <Card className={className}>
      <CardHeader>
        <CardTitle>{titre}</CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
        {action && <CardAction>{action}</CardAction>}
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  )
}

/** Trois dernières annonces destinées à l'utilisateur. */
export function AnnoncesRecentes({ annonces }: { annonces: Annonce[] }) {
  return (
    <Panneau
      titre="Dernières annonces"
      action={
        <Link to="/annonces" className="text-sm text-primary hover:underline">
          Toutes les annonces
        </Link>
      }
    >
      {annonces.length === 0 ? (
        <p className="text-sm text-muted-foreground">Aucune annonce.</p>
      ) : (
        <ul className="space-y-3">
          {annonces.map((a) => (
            <li key={a.id}>
              <p className="text-sm font-medium">{a.titre}</p>
              <p className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                <Badge variant="secondary" className="h-4 px-1.5 text-[10px]">
                  {a.cible === 'classe' ? a.classe?.nom : a.cible_libelle}
                </Badge>
                {new Date(a.date_publication).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} · {a.auteur}
              </p>
              <p className="mt-0.5 line-clamp-2 text-sm text-muted-foreground">{a.contenu}</p>
            </li>
          ))}
        </ul>
      )}
    </Panneau>
  )
}
