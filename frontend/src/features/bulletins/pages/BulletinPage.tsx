import { ArrowLeft, Lock, Pencil } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router'
import { PageHeader } from '@/components/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/auth-context'
import { useDialogState } from '@/hooks/use-dialog-state'
import { ErreurChargement } from '@/pages/ErrorPages'
import { telechargerPdfBulletin, useBulletin } from '../api'
import { AppreciationGeneraleDialog } from '../components/AppreciationGeneraleDialog'
import { BoutonPdf } from '../components/BoutonPdf'
import { BulletinDetail } from '../components/BulletinDetail'
import type { Bulletin } from '../types'

/** Bulletin d'un élève : aperçu pour la direction, consultation pour l'élève, ses parents et ses professeurs. */
export function BulletinPage() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const { user } = useAuth()
  const requete = useBulletin(id)
  const appreciation = useDialogState<Bulletin>()

  if (requete.isError) return <ErreurChargement error={requete.error} />
  if (!requete.data) return <Skeleton className="h-[32rem] w-full" />

  const bulletin = requete.data
  const gestion = user?.permissions.includes('bulletins.generer') ?? false
  const retour =
    gestion && bulletin.classe
      ? { to: `/bulletins?classe=${bulletin.classe.id}&periode=${bulletin.periode_id}`, libelle: `Bulletins de ${bulletin.classe.nom}` }
      : null

  return (
    <>
      {retour ? (
        <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
          <Link to={retour.to}>
            <ArrowLeft />
            {retour.libelle}
          </Link>
        </Button>
      ) : (
        <Button variant="ghost" size="sm" className="mb-2 -ml-2" onClick={() => navigate(-1)}>
          <ArrowLeft />
          Retour
        </Button>
      )}

      <PageHeader
        title={`${bulletin.eleve?.prenom} ${bulletin.eleve?.nom}`}
        description={`Bulletin du ${bulletin.periode?.libelle} · ${bulletin.classe?.nom} · ${bulletin.periode?.annee} · matricule ${bulletin.eleve?.matricule}`}
        actions={
          <>
            {bulletin.valide ? (
              <Badge className="h-8 px-3">
                <Lock />
                Validé
              </Badge>
            ) : (
              <Badge variant="outline" className="h-8 px-3">
                Brouillon — non visible des familles
              </Badge>
            )}
            <BoutonPdf
              size="default"
              libelle={bulletin.valide ? 'Télécharger le PDF' : 'PDF (projet)'}
              telecharger={() => telechargerPdfBulletin(bulletin.id)}
            />
          </>
        }
      />

      <BulletinDetail
        bulletin={bulletin}
        actionAppreciation={
          gestion &&
          !bulletin.valide && (
            <Button size="sm" variant="outline" onClick={() => appreciation.ouvrir(bulletin)}>
              <Pencil />
              Modifier
            </Button>
          )
        }
      />

      <AppreciationGeneraleDialog open={appreciation.open} onOpenChange={appreciation.setOpen} bulletin={appreciation.item} />
    </>
  )
}
