import { PageHeader } from '@/components/PageHeader'
import { useAuth } from '@/features/auth/auth-context'
import { ReleveNotes } from '../components/ReleveNotes'
import { EvaluationsProfPage } from './EvaluationsProfPage'

/** « Notes » : espace de saisie pour le professeur, relevé personnel pour l'élève. */
export function NotesPage() {
  const { user, hasRole } = useAuth()

  if (hasRole('professeur', 'admin')) return <EvaluationsProfPage />

  const eleveId = user?.profil.eleve_id
  return (
    <>
      <PageHeader title="Mes notes" description="Toutes vos notes, matière par matière." />
      {eleveId ? (
        <ReleveNotes eleveId={eleveId} />
      ) : (
        <p className="text-sm text-muted-foreground">Votre compte n’est rattaché à aucun dossier élève.</p>
      )}
    </>
  )
}
