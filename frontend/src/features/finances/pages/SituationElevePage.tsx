import { ArrowLeft } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/PageHeader'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/features/auth/auth-context'
import { useSituation } from '../api'
import { SituationFinanciere } from '../components/SituationFinanciere'

/** Situation financière d'un élève : consultation, encaissement, reçus. */
export function SituationElevePage() {
  const eleveId = Number(useParams().id)
  const { user } = useAuth()
  const gestion = user?.permissions.includes('paiements.gerer') ?? false
  const situation = useSituation(eleveId)
  const eleve = situation.data?.eleve

  return (
    <>
      <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
        <Link to="/paiements">
          <ArrowLeft />
          Paiements
        </Link>
      </Button>
      <PageHeader
        title={eleve ? `${eleve.prenom} ${eleve.nom}` : 'Situation financière'}
        description={situation.data ? `Matricule ${eleve?.matricule} · ${situation.data.classe.nom} · ${situation.data.annee}` : undefined}
        actions={
          eleve && (
            <Button variant="outline" asChild>
              <Link to={`/eleves/${eleve.id}`}>Fiche de l’élève</Link>
            </Button>
          )
        }
      />
      <SituationFinanciere eleveId={eleveId} gestion={gestion} />
    </>
  )
}
