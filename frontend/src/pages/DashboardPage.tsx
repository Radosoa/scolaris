import { PageHeader } from '@/components/PageHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/auth-context'
import { useTableauDeBord } from '@/features/tableau-de-bord/api'
import { AnnoncesRecentes } from '@/features/tableau-de-bord/components/Elements'
import { SuiviEleveCartes } from '@/features/tableau-de-bord/components/SuiviEleveCartes'
import { TableauDirection } from '@/features/tableau-de-bord/components/TableauDirection'
import { TableauProfesseur } from '@/features/tableau-de-bord/components/TableauProfesseur'
import { TableauSecretariat } from '@/features/tableau-de-bord/components/TableauSecretariat'
import type { TableauDeBord } from '@/features/tableau-de-bord/types'
import { ErreurChargement } from './ErrorPages'

const INTRODUCTIONS: Record<TableauDeBord['type'], string> = {
  direction: 'Vue d’ensemble de l’établissement : effectifs, résultats, absentéisme et finances.',
  secretariat: 'Les dossiers à traiter : absences, paiements et inscriptions.',
  professeur: 'Vos cours du jour, vos appels et les résultats de vos classes.',
  eleve: 'Vos résultats, vos absences et votre journée.',
  parent: 'Le suivi de la scolarité de vos enfants.',
}

/** Tableau de bord propre à chaque rôle. */
export function DashboardPage() {
  const { user } = useAuth()
  const requete = useTableauDeBord()
  const donnees = requete.data

  return (
    <>
      <PageHeader
        title={`Bonjour, ${user?.name.split(' ')[0] ?? ''}`}
        description={donnees ? INTRODUCTIONS[donnees.type] : undefined}
      />
      {requete.isError && <ErreurChargement error={requete.error} />}
      {requete.isPending && (
        <div className="space-y-4">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-72 w-full" />
        </div>
      )}
      {donnees && <Contenu donnees={donnees} />}
    </>
  )
}

function Contenu({ donnees }: { donnees: TableauDeBord }) {
  switch (donnees.type) {
    case 'direction':
      return <TableauDirection donnees={donnees} />
    case 'secretariat':
      return <TableauSecretariat donnees={donnees} />
    case 'professeur':
      return <TableauProfesseur donnees={donnees} />
    case 'eleve':
      return (
        <div className="space-y-4">
          {donnees.eleve ? (
            <SuiviEleveCartes suivi={donnees.eleve} />
          ) : (
            <p className="text-sm text-muted-foreground">Votre compte n’est rattaché à aucun dossier élève.</p>
          )}
          <AnnoncesRecentes annonces={donnees.annonces} />
        </div>
      )
    case 'parent':
      return (
        <div className="space-y-6">
          {donnees.enfants.length === 0 && <p className="text-sm text-muted-foreground">Aucun enfant n’est rattaché à votre compte.</p>}
          {donnees.enfants.map((enfant) => (
            <Card key={enfant.id}>
              <CardHeader>
                <CardTitle>
                  {enfant.nom_complet}
                  {enfant.classe && <span className="ml-2 text-sm font-normal text-muted-foreground">{enfant.classe}</span>}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <SuiviEleveCartes suivi={enfant} />
              </CardContent>
            </Card>
          ))}
          <AnnoncesRecentes annonces={donnees.annonces} />
        </div>
      )
  }
}
