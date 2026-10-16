import { Ban, FileDown, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useDialogState } from '@/hooks/use-dialog-state'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/utils'
import { ErreurChargement } from '@/pages/ErrorPages'
import { telechargerRecu, useSituation } from '../api'
import { formatEuros, progression } from '../format'
import type { Paiement, StatutEcheance } from '../types'
import { AnnulationDialog } from './AnnulationDialog'
import { EncaissementDialog } from './EncaissementDialog'

const STATUTS: Record<StatutEcheance, { libelle: string; variante: 'secondary' | 'destructive' | 'outline' }> = {
  reglee: { libelle: 'Réglée', variante: 'secondary' },
  en_retard: { libelle: 'En retard', variante: 'destructive' },
  a_venir: { libelle: 'À venir', variante: 'outline' },
}

/** Frais, échéancier et paiements d'un élève ; encaissement et annulation pour le secrétariat. */
export function SituationFinanciere({ eleveId, gestion }: { eleveId: number; gestion: boolean }) {
  const requete = useSituation(eleveId)
  const encaissement = useDialogState<null>()
  const annulation = useDialogState<Paiement>()

  if (requete.isError) return <ErreurChargement error={requete.error} />
  if (!requete.data) return <Skeleton className="h-96 w-full" />

  const s = requete.data
  const part = progression(s.paye, s.total)

  const colonnes: Column<Paiement>[] = [
    { header: 'Date', className: 'w-28', cell: (p) => formatDate(p.date) },
    { header: 'Reçu', cell: (p) => <span className={cn('font-mono text-xs', p.annule && 'line-through')}>{p.numero_recu}</span> },
    {
      header: 'Mode',
      className: 'hidden sm:table-cell',
      cell: (p) => (
        <span className="text-sm">
          {p.mode_libelle}
          {p.reference && <span className="text-muted-foreground"> · {p.reference}</span>}
        </span>
      ),
    },
    {
      header: 'Montant',
      className: 'text-right',
      cell: (p) => <span className={cn('tabular-nums', p.annule && 'text-muted-foreground line-through')}>{formatEuros(p.montant)}</span>,
    },
    {
      header: 'Statut',
      className: 'hidden md:table-cell',
      cell: (p) =>
        p.annule ? (
          <span className="text-xs text-destructive" title={p.motif_annulation ?? undefined}>
            Annulé · {p.motif_annulation}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">{p.encaisse_par ? `Encaissé par ${p.encaisse_par}` : 'Encaissé'}</span>
        ),
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (p) => (
        <RowActions
          label={p.numero_recu}
          actions={[
            { label: 'Télécharger le reçu', icon: FileDown, onSelect: () => telechargerRecu(p.id).catch((e) => toast.error(getErrorMessage(e))) },
            { label: 'Annuler le paiement', icon: Ban, onSelect: () => annulation.ouvrir(p), hidden: !gestion || p.annule, destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Chiffre libelle="Frais de l’année" valeur={formatEuros(s.total)} />
        <Chiffre libelle="Réglé" valeur={formatEuros(s.paye)} detail={`${part} %`} />
        <Chiffre libelle="Reste à payer" valeur={formatEuros(s.reste)} />
        <Chiffre libelle="En retard" valeur={formatEuros(s.retard)} alerte={s.retard > 0} />
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow={part} aria-valuemin={0} aria-valuemax={100} aria-label="Part des frais réglée">
        <div className="h-full rounded-full bg-emerald-600 transition-all" style={{ width: `${part}%` }} />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Frais {s.annee}</CardTitle>
            <CardDescription>Classe de {s.classe.nom}</CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableBody>
                {s.frais.map((f) => (
                  <TableRow key={f.id}>
                    <TableCell>{f.libelle}</TableCell>
                    <TableCell className="text-right tabular-nums">{formatEuros(f.montant)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
              <TableFooter>
                <TableRow>
                  <TableCell>Total</TableCell>
                  <TableCell className="text-right tabular-nums">{formatEuros(s.total)}</TableCell>
                </TableRow>
              </TableFooter>
            </Table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Échéancier</CardTitle>
            <CardDescription>Paiement en plusieurs tranches</CardDescription>
          </CardHeader>
          <CardContent>
            {s.echeances.length === 0 ? (
              <p className="text-sm text-muted-foreground">Aucun échéancier : les frais sont dus en totalité.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Tranche</TableHead>
                    <TableHead>Avant le</TableHead>
                    <TableHead className="text-right">Montant</TableHead>
                    <TableHead className="text-right">Statut</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {s.echeances.map((t) => (
                    <TableRow key={t.date_limite}>
                      <TableCell>
                        {t.libelle} <span className="text-xs text-muted-foreground">({t.pourcentage} %)</span>
                      </TableCell>
                      <TableCell>{formatDate(t.date_limite)}</TableCell>
                      <TableCell className="text-right tabular-nums">{formatEuros(t.montant)}</TableCell>
                      <TableCell className="text-right">
                        <Badge variant={STATUTS[t.statut].variante}>{STATUTS[t.statut].libelle}</Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Paiements</CardTitle>
          {gestion && s.reste > 0 && (
            <CardAction>
              <Button size="sm" onClick={() => encaissement.ouvrir()}>
                <Plus />
                Encaisser
              </Button>
            </CardAction>
          )}
        </CardHeader>
        <CardContent>
          <DataTable columns={colonnes} data={s.paiements} emptyMessage="Aucun paiement enregistré." />
        </CardContent>
      </Card>

      {gestion && <EncaissementDialog open={encaissement.open} onOpenChange={encaissement.setOpen} situation={s} />}
      <AnnulationDialog open={annulation.open} onOpenChange={annulation.setOpen} paiement={annulation.item} />
    </div>
  )
}

function Chiffre({ libelle, valeur, detail, alerte }: { libelle: string; valeur: string; detail?: string; alerte?: boolean }) {
  return (
    <Card size="sm">
      <CardHeader>
        <CardDescription>{libelle}</CardDescription>
        <p className={cn('text-xl font-semibold tabular-nums', alerte && 'text-destructive')}>
          {valeur}
          {detail && <span className="ml-1.5 text-sm font-normal text-muted-foreground">{detail}</span>}
        </p>
      </CardHeader>
    </Card>
  )
}
