import { Link } from 'react-router'
import { PageHeader } from '@/components/PageHeader'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDate } from '@/lib/format'
import { useMesEnfants } from '../api'
import { EleveAvatar } from '../components/EleveAvatar'

/** Espace parent : uniquement les enfants rattachés à son compte. */
export function MesEnfantsPage() {
  const enfants = useMesEnfants()

  return (
    <>
      <PageHeader title="Mes enfants" description="Suivez la scolarité de vos enfants." />

      {enfants.isPending && <Skeleton className="h-28 w-full max-w-md" />}
      {enfants.data?.length === 0 && (
        <p className="text-sm text-muted-foreground">
          Aucun enfant n’est rattaché à votre compte. Contactez le secrétariat de l’établissement.
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {enfants.data?.map((enfant) => (
          <Link key={enfant.id} to={`/eleves/${enfant.id}`} className="rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
            <Card className="h-full transition-colors hover:bg-accent/40">
              <CardHeader className="flex flex-row items-center gap-4">
                <EleveAvatar eleve={enfant} className="size-14" />
                <div>
                  <CardTitle>{enfant.nom_complet}</CardTitle>
                  <CardDescription>
                    {enfant.inscription_active?.statut === 'inscrit'
                      ? `Classe de ${enfant.inscription_active.classe?.nom}`
                      : 'Non inscrit cette année'}
                    {' · '}né(e) le {formatDate(enfant.date_naissance)}
                  </CardDescription>
                </div>
              </CardHeader>
            </Card>
          </Link>
        ))}
      </div>
    </>
  )
}
