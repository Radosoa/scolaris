import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableFooter, TableRow } from '@/components/ui/table'
import { formatDate } from '@/lib/format'
import { ErreurChargement } from '@/pages/ErrorPages'
import { useGrilleFrais, useSupprimerFrais } from '../api'
import { formatMontant } from '../format'
import type { LigneFrais } from '../types'
import { EcheancierDialog } from './EcheancierDialog'
import { FraisDialog } from './FraisDialog'

/** Frais par niveau et échéancier de l'année active ; modifiables par la direction seulement. */
export function FraisConfig({ modifiable }: { modifiable: boolean }) {
  const grille = useGrilleFrais()
  const suppression = useSupprimerFrais()
  const [edition, setEdition] = useState<{ open: boolean; frais: LigneFrais | null; niveau: { id: number; libelle: string } | null }>({
    open: false,
    frais: null,
    niveau: null,
  })
  const [aSupprimer, setASupprimer] = useState<LigneFrais | null>(null)
  const [echeancier, setEcheancier] = useState(false)

  if (grille.isError) return <ErreurChargement error={grille.error} />
  if (!grille.data) return <Skeleton className="h-80 w-full" />

  const { data: niveaux, meta } = grille.data

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Échéancier {meta.annee.libelle}</CardTitle>
          <CardDescription>Les familles peuvent régler en plusieurs fois, avant chaque date limite.</CardDescription>
          {modifiable && (
            <CardAction>
              <Button size="sm" variant="outline" onClick={() => setEcheancier(true)}>
                <CalendarClock />
                Modifier l’échéancier
              </Button>
            </CardAction>
          )}
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          {meta.echeances.length === 0 && <p className="text-sm text-muted-foreground">Aucun échéancier : les frais sont dus en totalité dès la rentrée.</p>}
          {meta.echeances.map((e) => (
            <Badge key={e.date_limite} variant="secondary" className="h-7 px-3">
              {e.libelle} · {e.pourcentage} % avant le {formatDate(e.date_limite)}
            </Badge>
          ))}
        </CardContent>
      </Card>

      <div className="grid gap-4 md:grid-cols-2">
        {niveaux.map(({ niveau, frais, total }) => (
          <Card key={niveau.id}>
            <CardHeader>
              <CardTitle>Niveau {niveau.libelle}</CardTitle>
              <CardDescription>{formatMontant(total)} par élève et par an</CardDescription>
              {modifiable && (
                <CardAction>
                  <Button size="sm" variant="ghost" onClick={() => setEdition({ open: true, frais: null, niveau })}>
                    <Plus />
                    Ajouter
                  </Button>
                </CardAction>
              )}
            </CardHeader>
            <CardContent>
              {frais.length === 0 ? (
                <p className="text-sm text-muted-foreground">Aucun frais pour ce niveau.</p>
              ) : (
                <Table>
                  <TableBody>
                    {frais.map((f) => (
                      <TableRow key={f.id}>
                        <TableCell>{f.libelle}</TableCell>
                        <TableCell className="text-right tabular-nums">{formatMontant(f.montant)}</TableCell>
                        {modifiable && (
                          <TableCell className="w-12 text-right">
                            <RowActions
                              label={f.libelle}
                              actions={[
                                { label: 'Modifier', icon: Pencil, onSelect: () => setEdition({ open: true, frais: f, niveau }) },
                                { label: 'Supprimer', icon: Trash2, onSelect: () => setASupprimer(f), destructive: true },
                              ]}
                            />
                          </TableCell>
                        )}
                      </TableRow>
                    ))}
                  </TableBody>
                  <TableFooter>
                    <TableRow>
                      <TableCell>Total</TableCell>
                      <TableCell className="text-right tabular-nums">{formatMontant(total)}</TableCell>
                      {modifiable && <TableCell />}
                    </TableRow>
                  </TableFooter>
                </Table>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      {modifiable && (
        <>
          <FraisDialog
            open={edition.open}
            onOpenChange={(open) => setEdition((e) => ({ ...e, open }))}
            frais={edition.frais}
            niveau={edition.niveau}
            anneeId={meta.annee.id}
          />
          <EcheancierDialog open={echeancier} onOpenChange={setEcheancier} annee={meta.annee} echeances={meta.echeances} />
          <ConfirmDialog
            open={!!aSupprimer}
            onOpenChange={(open) => !open && setASupprimer(null)}
            title={`Supprimer « ${aSupprimer?.libelle} » ?`}
            description={`Le total dû par les familles de ce niveau baissera de ${formatMontant(aSupprimer?.montant)}.`}
            confirmLabel="Supprimer"
            destructive
            onConfirm={async () => {
              await suppression.mutateAsync(aSupprimer!.id)
              toast.success('Frais supprimés.')
            }}
          />
        </>
      )}
    </div>
  )
}
