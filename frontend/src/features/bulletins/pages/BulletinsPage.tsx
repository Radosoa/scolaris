import { PageHeader } from '@/components/PageHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/auth-context'
import { useMesEnfants } from '@/features/personnes/api'
import { ListeBulletins } from '../components/ListeBulletins'
import { AppreciationsPage } from './AppreciationsPage'
import { BulletinsClassePage } from './BulletinsClassePage'

/**
 * « Bulletins » selon le rôle : gestion par classe (direction), appréciations (professeur),
 * consultation (élève, parent).
 */
export function BulletinsPage() {
  const { user, hasRole } = useAuth()

  if (user?.permissions.includes('bulletins.generer')) return <BulletinsClassePage />
  if (hasRole('professeur')) return <AppreciationsPage />
  if (hasRole('parent')) return <BulletinsEnfants />

  const eleveId = user?.profil.eleve_id
  return (
    <>
      <PageHeader title="Mes bulletins" description="Vos bulletins validés, à consulter ou à télécharger en PDF." />
      {eleveId ? (
        <ListeBulletins eleveId={eleveId} />
      ) : (
        <p className="text-sm text-muted-foreground">Votre compte n’est rattaché à aucun dossier élève.</p>
      )}
    </>
  )
}

function BulletinsEnfants() {
  const enfants = useMesEnfants()

  return (
    <>
      <PageHeader title="Bulletins" description="Les bulletins validés de vos enfants." />
      {enfants.isPending && <Skeleton className="h-32 w-full" />}
      {enfants.data?.length === 0 && <p className="text-sm text-muted-foreground">Aucun enfant n’est rattaché à votre compte.</p>}
      <div className="space-y-4">
        {enfants.data?.map((enfant) => (
          <Card key={enfant.id}>
            <CardHeader>
              <CardTitle>{enfant.nom_complet}</CardTitle>
            </CardHeader>
            <CardContent>
              <ListeBulletins eleveId={enfant.id} />
            </CardContent>
          </Card>
        ))}
      </div>
    </>
  )
}
