import { ArrowLeft, Camera, KeyRound, Loader2, Pencil, School, Trash2, Users, Wallet } from 'lucide-react'
import { useRef } from 'react'
import { Link, useParams } from 'react-router'
import { toast } from 'sonner'
import { PageHeader } from '@/components/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/auth-context'
import { ListeBulletins } from '@/features/bulletins/components/ListeBulletins'
import { ReleveNotes } from '@/features/notes/components/ReleveNotes'
import { AbsencesEleve } from '@/features/vie-scolaire/components/AbsencesEleve'
import { useDialogState } from '@/hooks/use-dialog-state'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { ErreurChargement } from '@/pages/ErrorPages'
import { eleves, usePhotoEleve } from '../api'
import { CompteDialog } from '../components/CompteDialog'
import { EleveAvatar } from '../components/EleveAvatar'
import { EleveFormDialog } from '../components/EleveFormDialog'
import { InscriptionDialog } from '../components/InscriptionDialog'
import { ParentsDialog } from '../components/ParentsDialog'
import type { Eleve, Inscription } from '../types'

function Info({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="text-sm">{children || '—'}</dd>
    </div>
  )
}

export function EleveFichePage() {
  const id = Number(useParams().id)
  const requete = eleves.useDetail(id)
  const { user, hasRole } = useAuth()
  const peutGerer = user?.permissions.includes('eleves.gerer') ?? false
  const peutInscrire = user?.permissions.includes('inscriptions.gerer') ?? false
  // Le secrétariat et les parents (de cet élève : l'API le vérifie) suivent les paiements
  const voitFinances = (user?.permissions.includes('paiements.gerer') ?? false) || hasRole('parent')
  const retour = hasRole('parent')
    ? { to: '/mes-enfants', label: 'Mes enfants' }
    : hasRole('eleve')
      ? { to: '/tableau-de-bord', label: 'Tableau de bord' }
      : { to: '/eleves', label: 'Élèves' }

  const edition = useDialogState<null>()
  const parents = useDialogState<null>()
  const compte = useDialogState<null>()
  const inscription = useDialogState<Inscription>()

  if (requete.isError) return <ErreurChargement error={requete.error} />
  if (!requete.data) return <Skeleton className="h-96 w-full" />

  const eleve = requete.data
  const active = eleve.inscription_active

  return (
    <>
      <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
        <Link to={retour.to}>
          <ArrowLeft />
          {retour.label}
        </Link>
      </Button>

      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <Photo eleve={eleve} modifiable={peutGerer} />
        <div className="flex-1">
          <PageHeader
            title={`${eleve.prenom} ${eleve.nom.toUpperCase()}`}
            description={`Matricule ${eleve.matricule}${active?.statut === 'inscrit' ? ` · ${active.classe?.nom}` : ''}`}
            actions={
              <>
                {voitFinances && active && (
                  <Button variant="outline" asChild>
                    <Link to={`/paiements/eleves/${eleve.id}`}>
                      <Wallet />
                      Situation financière
                    </Link>
                  </Button>
                )}
                {peutGerer && (
                  <Button variant="outline" onClick={() => edition.ouvrir()}>
                    <Pencil />
                    Modifier
                  </Button>
                )}
              </>
            }
          />
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Identité</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="grid grid-cols-2 gap-4">
              <Info label="Nom">{eleve.nom}</Info>
              <Info label="Prénom">{eleve.prenom}</Info>
              <Info label="Sexe">{eleve.sexe === 'F' ? 'Fille' : 'Garçon'}</Info>
              <Info label="Date de naissance">{formatDate(eleve.date_naissance)}</Info>
              <Info label="Lieu de naissance">{eleve.lieu_naissance}</Info>
              <Info label="Compte de connexion">
                {eleve.compte ? (
                  eleve.compte.email
                ) : peutGerer ? (
                  <Button variant="link" className="h-auto p-0" onClick={() => compte.ouvrir()}>
                    <KeyRound />
                    Créer le compte
                  </Button>
                ) : (
                  'Aucun'
                )}
              </Info>
            </dl>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Parents</CardTitle>
            {peutGerer && (
              <CardAction>
                <Button variant="outline" size="sm" onClick={() => parents.ouvrir()}>
                  <Users />
                  Gérer
                </Button>
              </CardAction>
            )}
          </CardHeader>
          <CardContent>
            {eleve.tuteurs?.length ? (
              <ul className="divide-y">
                {eleve.tuteurs.map((t) => (
                  <li key={t.id} className="flex items-center justify-between gap-2 py-2 first:pt-0 last:pb-0">
                    <div>
                      <p className="text-sm font-medium">{t.nom_complet}</p>
                      <p className="text-xs text-muted-foreground">
                        {t.telephone}
                        {t.email && ` · ${t.email}`}
                      </p>
                    </div>
                    <Badge variant="outline">{t.lien_libelle}</Badge>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">Aucun parent rattaché.</p>
            )}
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Scolarité</CardTitle>
            {peutInscrire && !active && (
              <CardAction>
                <Button size="sm" onClick={() => inscription.ouvrir(null)}>
                  <School />
                  Inscrire
                </Button>
              </CardAction>
            )}
          </CardHeader>
          <CardContent>
            {eleve.inscriptions?.length ? (
              <ul className="divide-y">
                {eleve.inscriptions.map((i) => (
                  <li key={i.id} className="flex items-center justify-between gap-2 py-2 first:pt-0 last:pb-0">
                    <div>
                      <p className="text-sm font-medium">
                        {i.annee_scolaire?.libelle} · {i.classe?.nom}
                      </p>
                      <p className="text-xs text-muted-foreground">Inscrit le {formatDate(i.date_inscription)}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge variant={i.statut === 'inscrit' ? 'default' : 'secondary'}>{i.statut_libelle}</Badge>
                      {peutInscrire && i.annee_scolaire?.active && (
                        <Button variant="ghost" size="icon" aria-label="Modifier l’inscription" onClick={() => inscription.ouvrir(i)}>
                          <Pencil />
                        </Button>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">Aucune inscription.</p>
            )}
          </CardContent>
        </Card>

        {eleve.inscriptions && eleve.inscriptions.length > 0 && (
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Notes</CardTitle>
            </CardHeader>
            <CardContent>
              <ReleveNotes eleveId={eleve.id} />
            </CardContent>
          </Card>
        )}

        {eleve.inscriptions && eleve.inscriptions.length > 0 && (
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Bulletins</CardTitle>
            </CardHeader>
            <CardContent>
              <ListeBulletins eleveId={eleve.id} />
            </CardContent>
          </Card>
        )}

        {eleve.inscriptions && eleve.inscriptions.length > 0 && (
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Absences et retards</CardTitle>
            </CardHeader>
            <CardContent>
              <AbsencesEleve eleveId={eleve.id} />
            </CardContent>
          </Card>
        )}
      </div>

      <EleveFormDialog open={edition.open} onOpenChange={edition.setOpen} eleve={eleve} />
      <ParentsDialog open={parents.open} onOpenChange={parents.setOpen} eleve={eleve} />
      <CompteDialog open={compte.open} onOpenChange={compte.setOpen} type="eleves" id={eleve.id} nom={eleve.nom_complet} />
      {peutInscrire && (
      <InscriptionDialog
        open={inscription.open}
        onOpenChange={inscription.setOpen}
        eleveId={eleve.id}
        nomEleve={eleve.nom_complet}
        inscription={inscription.item}
      />
      )}
    </>
  )
}

/** Photo de l'élève, avec envoi et suppression pour l'administration. */
function Photo({ eleve, modifiable }: { eleve: Eleve; modifiable: boolean }) {
  const { envoyer, supprimer } = usePhotoEleve(eleve.id)
  const champ = useRef<HTMLInputElement>(null)
  const enCours = envoyer.isPending || supprimer.isPending

  const choisir = async (fichier: File | undefined) => {
    if (!fichier) return
    try {
      await envoyer.mutateAsync(fichier)
      toast.success('Photo mise à jour.')
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  return (
    <div className="flex items-center gap-3">
      <div className="relative">
        <EleveAvatar eleve={eleve} className="size-20 text-lg" />
        {enCours && (
          <div className="absolute inset-0 flex items-center justify-center rounded-full bg-background/70">
            <Loader2 className="size-5 animate-spin" />
          </div>
        )}
      </div>
      {modifiable && (
        <div className="flex flex-col gap-1">
          <Button variant="outline" size="sm" onClick={() => champ.current?.click()} disabled={enCours}>
            <Camera />
            {eleve.photo_url ? 'Changer' : 'Ajouter une photo'}
          </Button>
          {eleve.photo_url && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => supprimer.mutate(undefined, { onError: (e) => toast.error(getErrorMessage(e)) })}
              disabled={enCours}
            >
              <Trash2 />
              Retirer
            </Button>
          )}
          <input
            ref={champ}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(event) => {
              choisir(event.target.files?.[0])
              event.target.value = ''
            }}
          />
        </div>
      )}
    </div>
  )
}
