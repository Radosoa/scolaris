import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { Pagination } from '@/components/data-table/Pagination'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/features/auth/auth-context'
import { useDialogState } from '@/hooks/use-dialog-state'
import { ErreurChargement } from '@/pages/ErrorPages'
import { annonces } from '../api'
import { AnnonceFormDialog } from '../components/AnnonceFormDialog'
import type { Annonce } from '../types'

const dateHeure = (iso: string) => new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })

/** Annonces qui concernent l'utilisateur ; publication ciblée par la direction et le secrétariat. */
export function AnnoncesPage() {
  const { user } = useAuth()
  const peutPublier = user?.permissions.includes('annonces.publier') ?? false
  const liste = useListState(10)
  const requete = annonces.useList(liste.params)
  const suppression = annonces.useRemove()
  const formulaire = useDialogState<Annonce>()
  const aSupprimer = useDialogState<Annonce>()

  return (
    <>
      <PageHeader
        title="Annonces"
        description={peutPublier ? 'Informez tout l’établissement, les professeurs, les parents ou une classe.' : 'Les informations de l’établissement qui vous concernent.'}
        actions={
          peutPublier && (
            <Button onClick={() => formulaire.ouvrir()}>
              <Plus />
              Nouvelle annonce
            </Button>
          )
        }
      />

      <div className="mb-4">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Rechercher une annonce…" />
      </div>

      {requete.isError && <ErreurChargement error={requete.error} />}
      {requete.isPending && <Skeleton className="h-48 w-full" />}
      {requete.data?.data.length === 0 && <p className="text-sm text-muted-foreground">Aucune annonce pour le moment.</p>}

      <div className="space-y-3">
        {requete.data?.data.map((a) => (
          <Card key={a.id} className={a.publiee ? undefined : 'border-dashed bg-muted/40'}>
            <CardHeader>
              <CardTitle>{a.titre}</CardTitle>
              <CardDescription className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{a.cible === 'classe' ? `Classe de ${a.classe?.nom}` : a.cible_libelle}</Badge>
                {!a.publiee && (
                  <Badge variant="outline">
                    <CalendarClock />
                    Programmée
                  </Badge>
                )}
                <span>
                  {a.auteur} · {dateHeure(a.date_publication)}
                </span>
              </CardDescription>
              {a.modifiable && (
                <CardAction>
                  <RowActions
                    label={a.titre}
                    actions={[
                      { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(a) },
                      { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(a), destructive: true },
                    ]}
                  />
                </CardAction>
              )}
            </CardHeader>
            <CardContent>
              <p className="text-sm whitespace-pre-line">{a.contenu}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      {requete.data && requete.data.meta.last_page > 1 && (
        <div className="mt-4">
          <Pagination meta={requete.data.meta} onPageChange={liste.setPage} />
        </div>
      )}

      {peutPublier && <AnnonceFormDialog open={formulaire.open} onOpenChange={formulaire.setOpen} annonce={formulaire.item} />}
      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer « ${aSupprimer.item?.titre} » ?`}
        description="L’annonce disparaîtra pour tous ses destinataires."
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Annonce supprimée.')
        }}
      />
    </>
  )
}
