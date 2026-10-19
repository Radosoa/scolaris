import { CheckCircle2, Download, FileSpreadsheet, Loader2, Upload, XCircle } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { telechargerModeleImport, useAnalyserImport, useImporterEleves } from '../api'
import type { AnalyseImport, ResultatImport } from '../types'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Import en deux temps : analyse du fichier (rapport ligne par ligne, rien n'est
 * enregistré), puis import des seules lignes valides.
 */
export function ImportElevesDialog({ open, onOpenChange }: Props) {
  const [fichier, setFichier] = useState<File | null>(null)
  const [analyse, setAnalyse] = useState<AnalyseImport | null>(null)
  const [resultat, setResultat] = useState<ResultatImport | null>(null)
  const analyser = useAnalyserImport()
  const importer = useImporterEleves()
  const enCours = analyser.isPending || importer.isPending

  const reinitialiser = () => {
    setFichier(null)
    setAnalyse(null)
    setResultat(null)
    analyser.reset()
  }

  const fermer = (ouvert: boolean) => {
    if (enCours) return
    if (!ouvert) reinitialiser()
    onOpenChange(ouvert)
  }

  const choisir = async (choisi: File | undefined) => {
    if (!choisi) return
    setFichier(choisi)
    setAnalyse(null)
    try {
      setAnalyse(await analyser.mutateAsync(choisi))
    } catch {
      // Message affiché sous la zone de dépôt
    }
  }

  const lancerImport = async () => {
    if (!fichier) return
    try {
      const rapport = await importer.mutateAsync(fichier)
      setResultat(rapport)
      toast.success(`${rapport.importes} élève(s) importé(s).`)
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  // En 422, Laravel place le premier message de validation dans « message »
  const erreurAnalyse = analyser.error ? getErrorMessage(analyser.error) : null

  return (
    <Dialog open={open} onOpenChange={fermer}>
      <DialogContent className="min-w-0 sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Importer des élèves</DialogTitle>
          <DialogDescription>
            Fichier Excel ou CSV avec les colonnes nom, prenom, sexe (M/F), date_naissance (JJ/MM/AAAA), lieu_naissance et
            classe (facultative, pour inscrire directement sur l’année active).
          </DialogDescription>
        </DialogHeader>

        {resultat ? (
          <div className="space-y-3">
            <Alert>
              <CheckCircle2 />
              <AlertDescription>
                {resultat.importes} élève(s) importé(s) sur {resultat.total} ligne(s).
                {resultat.erreurs.length > 0 && ` ${resultat.erreurs.length} ligne(s) ignorée(s).`}
              </AlertDescription>
            </Alert>
            {resultat.erreurs.length > 0 && <RapportErreurs lignes={resultat.erreurs} />}
          </div>
        ) : (
          <div className="min-w-0 space-y-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
              <Button variant="outline" size="sm" onClick={() => telechargerModeleImport().catch((e) => toast.error(getErrorMessage(e)))}>
                <Download />
                Télécharger le modèle
              </Button>
              <label className="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border border-dashed px-3 py-2 text-sm hover:bg-accent">
                <FileSpreadsheet className="size-4 text-muted-foreground" />
                <span className="truncate">{fichier ? fichier.name : 'Choisir un fichier (.xlsx, .xls, .csv)'}</span>
                <input
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  className="sr-only"
                  onChange={(event) => {
                    choisir(event.target.files?.[0])
                    event.target.value = ''
                  }}
                />
              </label>
            </div>

            {analyser.isPending && (
              <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                Analyse du fichier…
              </p>
            )}
            {erreurAnalyse && !analyser.isPending && <p className="text-sm text-destructive">{erreurAnalyse}</p>}

            {analyse && (
              <>
                <div className="flex flex-wrap gap-2 text-sm">
                  <Badge variant="secondary">{analyse.total} ligne(s)</Badge>
                  <Badge>{analyse.valides} valide(s)</Badge>
                  {analyse.total - analyse.valides > 0 && (
                    <Badge variant="destructive">{analyse.total - analyse.valides} en erreur</Badge>
                  )}
                </div>
                <div className="max-h-72 overflow-auto rounded-lg border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-14">Ligne</TableHead>
                        <TableHead>Élève</TableHead>
                        <TableHead className="hidden sm:table-cell">Naissance</TableHead>
                        <TableHead className="hidden sm:table-cell">Classe</TableHead>
                        <TableHead>Résultat</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {analyse.lignes.map((ligne) => (
                        <TableRow key={ligne.ligne}>
                          <TableCell className="tabular-nums">{ligne.ligne}</TableCell>
                          <TableCell>
                            {ligne.donnees.prenom} {ligne.donnees.nom}
                          </TableCell>
                          <TableCell className="hidden sm:table-cell">{formatDate(ligne.donnees.date_naissance)}</TableCell>
                          <TableCell className="hidden sm:table-cell">{ligne.donnees.classe ?? '—'}</TableCell>
                          <TableCell className="whitespace-normal">
                            {ligne.erreurs.length === 0 ? (
                              <span className="flex items-center gap-1 text-sm text-primary">
                                <CheckCircle2 className="size-4" /> Prêt
                              </span>
                            ) : (
                              <ul className="space-y-0.5 text-sm text-destructive">
                                {ligne.erreurs.map((erreur) => (
                                  <li key={erreur} className="flex gap-1">
                                    <XCircle className="mt-0.5 size-4 shrink-0" />
                                    {erreur}
                                  </li>
                                ))}
                              </ul>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </>
            )}
          </div>
        )}

        <DialogFooter>
          {resultat ? (
            <>
              <Button variant="secondary" onClick={reinitialiser}>
                Nouvel import
              </Button>
              <Button onClick={() => fermer(false)}>Terminer</Button>
            </>
          ) : (
            <>
              <Button variant="secondary" onClick={() => fermer(false)} disabled={enCours}>
                Annuler
              </Button>
              <Button onClick={lancerImport} disabled={!analyse || analyse.valides === 0 || enCours}>
                {importer.isPending ? <Loader2 className="animate-spin" /> : <Upload />}
                Importer {analyse ? `${analyse.valides} élève(s)` : ''}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function RapportErreurs({ lignes }: { lignes: { ligne: number; erreurs: string[] }[] }) {
  return (
    <div className="max-h-60 overflow-auto rounded-lg border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-14">Ligne</TableHead>
            <TableHead>Motif du rejet</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {lignes.map((ligne) => (
            <TableRow key={ligne.ligne}>
              <TableCell className="tabular-nums">{ligne.ligne}</TableCell>
              <TableCell className="text-sm whitespace-normal text-destructive">{ligne.erreurs.join(' · ')}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
