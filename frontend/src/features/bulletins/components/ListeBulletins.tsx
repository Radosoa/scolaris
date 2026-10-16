import { Eye } from 'lucide-react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { telechargerPdfBulletin, useBulletinsEleve } from '../api'
import { formatRang } from '../format'
import { BoutonPdf } from './BoutonPdf'
import { Moyenne } from './Moyenne'

/** Bulletins consultables d'un élève (élève, parent, fiche élève), du plus récent au plus ancien. */
export function ListeBulletins({ eleveId }: { eleveId: number }) {
  const bulletins = useBulletinsEleve(eleveId)

  if (bulletins.isPending) return <Skeleton className="h-16 w-full" />
  if (bulletins.isError) return <p className="text-sm text-muted-foreground">Bulletins indisponibles.</p>
  if (bulletins.data.length === 0) {
    return <p className="text-sm text-muted-foreground">Aucun bulletin publié pour le moment.</p>
  }

  return (
    <ul className="divide-y rounded-lg border bg-background">
      {bulletins.data.map((b) => (
        <li key={b.id} className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
          <div className="min-w-0 flex-1">
            <p className="font-medium">
              {b.periode?.libelle}
              {!b.valide && (
                <Badge variant="outline" className="ml-2">
                  Brouillon
                </Badge>
              )}
            </p>
            <p className="text-xs text-muted-foreground">
              {b.classe?.nom} · {b.periode?.annee}
            </p>
          </div>
          <div className="flex items-center gap-4 text-sm">
            <span>
              <Moyenne valeur={b.moyenne} className="text-base" /> <span className="text-muted-foreground">/ 20</span>
            </span>
            <span className="text-muted-foreground">
              {formatRang(b.rang)} / {b.effectif}
            </span>
            {b.mention && <Badge variant="secondary" className="hidden md:inline-flex">{b.mention}</Badge>}
          </div>
          <div className="flex gap-2">
            <Button asChild size="sm" variant="outline">
              <Link to={`/bulletins/${b.id}`}>
                <Eye />
                Consulter
              </Link>
            </Button>
            <BoutonPdf telecharger={() => telechargerPdfBulletin(b.id)} />
          </div>
        </li>
      ))}
    </ul>
  )
}
