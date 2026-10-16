import type { ReactNode } from 'react'
import { Badge } from '@/components/ui/badge'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatNombre } from '@/lib/format'
import { formatHeures, formatMoyenne, formatRang } from '../format'
import type { Bulletin } from '../types'
import { Moyenne } from './Moyenne'

interface Props {
  bulletin: Bulletin
  /** Action affichée à côté de l'appréciation générale (ex. « Modifier » pour la direction) */
  actionAppreciation?: ReactNode
}

/** Bulletin complet à l'écran : résultats, tableau des matières, vie scolaire, appréciation générale. */
export function BulletinDetail({ bulletin, actionAppreciation }: Props) {
  const lignes = bulletin.lignes ?? []
  const notees = lignes.filter((l) => l.moyenne !== null)
  const totalCoefficients = notees.reduce((total, l) => total + l.coefficient, 0)

  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Resume titre="Moyenne générale">
          <p className="text-3xl font-semibold">
            <Moyenne valeur={bulletin.moyenne} />
            <span className="ml-1 text-base font-normal text-muted-foreground">/ 20</span>
          </p>
          {bulletin.mention && <Badge className="mt-2">{bulletin.mention}</Badge>}
        </Resume>
        <Resume titre="Rang">
          <p className="text-3xl font-semibold tabular-nums">{formatRang(bulletin.rang)}</p>
          <p className="mt-1 text-sm text-muted-foreground">
            sur {bulletin.effectif} élève{bulletin.effectif > 1 && 's'}
            {bulletin.ex_aequo && ' · ex æquo'}
          </p>
        </Resume>
        <Resume titre="Classe">
          <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
            <dt className="text-muted-foreground">Moyenne</dt>
            <dd className="text-right font-medium tabular-nums">{formatMoyenne(bulletin.moyenne_classe)}</dd>
            <dt className="text-muted-foreground">Plus forte</dt>
            <dd className="text-right tabular-nums">{formatMoyenne(bulletin.moyenne_max)}</dd>
            <dt className="text-muted-foreground">Plus faible</dt>
            <dd className="text-right tabular-nums">{formatMoyenne(bulletin.moyenne_min)}</dd>
          </dl>
        </Resume>
        <Resume titre="Vie scolaire">
          <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
            <dt className="text-muted-foreground">Absences justifiées</dt>
            <dd className="text-right tabular-nums">{formatHeures(bulletin.absences.heures_justifiees)}</dd>
            <dt className="text-muted-foreground">Non justifiées</dt>
            <dd className="text-right font-medium tabular-nums">{formatHeures(bulletin.absences.heures_non_justifiees)}</dd>
            <dt className="text-muted-foreground">Retards</dt>
            <dd className="text-right tabular-nums">{bulletin.absences.retards}</dd>
          </dl>
        </Resume>
      </div>

      <div className="overflow-x-auto rounded-lg border bg-background">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Matière</TableHead>
              <TableHead className="text-right">Coef.</TableHead>
              <TableHead className="text-right">Moyenne</TableHead>
              <TableHead className="hidden text-right md:table-cell">Classe</TableHead>
              <TableHead className="hidden text-right lg:table-cell">Min – Max</TableHead>
              <TableHead className="text-right">Rang</TableHead>
              <TableHead className="hidden sm:table-cell">Appréciation</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {lignes.map((ligne) => (
              <TableRow key={ligne.id}>
                <TableCell className="whitespace-normal">
                  <p className="font-medium">{ligne.matiere.nom}</p>
                  {ligne.enseignant && <p className="text-xs text-muted-foreground">{ligne.enseignant}</p>}
                  {ligne.appreciation && <p className="mt-1 text-xs italic sm:hidden">{ligne.appreciation}</p>}
                </TableCell>
                <TableCell className="text-right tabular-nums">{formatNombre(ligne.coefficient)}</TableCell>
                <TableCell className="text-right">
                  {ligne.moyenne === null ? <span className="text-xs text-muted-foreground">Non noté</span> : <Moyenne valeur={ligne.moyenne} />}
                </TableCell>
                <TableCell className="hidden text-right text-muted-foreground tabular-nums md:table-cell">
                  {formatMoyenne(ligne.moyenne_classe)}
                </TableCell>
                <TableCell className="hidden text-right text-muted-foreground tabular-nums lg:table-cell">
                  {ligne.moyenne_min === null ? '—' : `${formatMoyenne(ligne.moyenne_min)} – ${formatMoyenne(ligne.moyenne_max)}`}
                </TableCell>
                <TableCell className="text-right tabular-nums">{ligne.rang === null ? '—' : formatRang(ligne.rang)}</TableCell>
                <TableCell className="hidden max-w-80 whitespace-normal text-sm italic sm:table-cell">
                  {ligne.appreciation ?? <span className="not-italic text-muted-foreground">—</span>}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
          <TableFooter>
            <TableRow>
              <TableCell>Total des coefficients notés</TableCell>
              <TableCell className="text-right tabular-nums">{formatNombre(totalCoefficients)}</TableCell>
              <TableCell colSpan={5} className="text-xs font-normal text-muted-foreground">
                Moyennes sur 20 · devoirs et interrogations 40 %, composition 60 % · matières non notées exclues
              </TableCell>
            </TableRow>
          </TableFooter>
        </Table>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Appréciation générale</CardTitle>
          {actionAppreciation && <CardAction>{actionAppreciation}</CardAction>}
        </CardHeader>
        <CardContent>
          {bulletin.appreciation_generale ? (
            <p className="text-sm whitespace-pre-line">{bulletin.appreciation_generale}</p>
          ) : (
            <CardDescription>Aucune appréciation générale.</CardDescription>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function Resume({ titre, children }: { titre: string; children: ReactNode }) {
  return (
    <Card size="sm">
      <CardHeader>
        <CardDescription>{titre}</CardDescription>
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  )
}
