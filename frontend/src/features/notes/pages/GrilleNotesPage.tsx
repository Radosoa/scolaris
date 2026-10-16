import { ArrowLeft, Keyboard, Loader2, Lock, RotateCcw, Save } from 'lucide-react'
import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { Link, useBlocker, useParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PageHeader } from '@/components/PageHeader'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/utils'
import { ErreurChargement } from '@/pages/ErrorPages'
import axios from 'axios'
import { useEnregistrerNotes, useGrille } from '../api'
import {
  formatNote,
  lignesInitiales,
  lignesModifiees,
  lireNote,
  statistiquesSaisie,
  versPayload,
  type LigneEdition,
} from '../saisie'
import type { Grille } from '../types'

export function GrilleNotesPage() {
  const id = Number(useParams().id)
  const requete = useGrille(id)

  if (requete.isError) return <ErreurChargement error={requete.error} />
  if (!requete.data) return <Skeleton className="h-[32rem] w-full" />

  // key : repartir d'une saisie propre quand la grille enregistrée change
  return <Saisie key={requete.dataUpdatedAt} grille={requete.data} />
}

function Saisie({ grille }: { grille: Grille }) {
  const { evaluation, eleves } = grille
  const bareme = evaluation.bareme
  const verrouillee = evaluation.verrouillee ?? false
  const enregistrement = useEnregistrerNotes(evaluation.id)

  const [lignes, setLignes] = useState<LigneEdition[]>(() => lignesInitiales(eleves))
  const [erreursServeur, setErreursServeur] = useState<Record<number, string>>({})
  const champs = useRef<(HTMLInputElement | null)[]>([])

  const modifiees = useMemo(() => lignesModifiees(lignes, eleves, bareme), [lignes, eleves, bareme])
  const stats = useMemo(() => statistiquesSaisie(lignes, bareme), [lignes, bareme])
  const erreurs = useMemo(
    () =>
      Object.fromEntries(
        lignes.flatMap((ligne) => {
          if (ligne.absent) return []
          const lecture = lireNote(ligne.texte, bareme)
          return lecture.ok ? [] : [[ligne.eleve_id, lecture.erreur]]
        }),
      ) as Record<number, string>,
    [lignes, bareme],
  )
  const nbErreurs = Object.keys(erreurs).length
  const aEnregistrer = modifiees.length > 0

  // Garde-fou : quitter la page (lien interne ou fermeture d'onglet) avec des notes non enregistrées
  const blocker = useBlocker(({ currentLocation, nextLocation }) => aEnregistrer && currentLocation.pathname !== nextLocation.pathname)
  useEffect(() => {
    if (!aEnregistrer) return
    const avertir = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener('beforeunload', avertir)
    return () => window.removeEventListener('beforeunload', avertir)
  }, [aEnregistrer])

  const modifier = (index: number, changement: Partial<LigneEdition>) => {
    setLignes((actuelles) => actuelles.map((l, i) => (i === index ? { ...l, ...changement } : l)))
    setErreursServeur((e) => {
      const { [lignes[index].eleve_id]: _retiree, ...reste } = e
      return reste
    })
  }

  const focaliser = (index: number) => {
    const champ = champs.current[Math.max(0, Math.min(lignes.length - 1, index))]
    champ?.focus()
    champ?.select()
  }

  const enregistrer = useCallback(async () => {
    if (!aEnregistrer || nbErreurs > 0 || verrouillee) return
    const envoyees = modifiees
    try {
      await enregistrement.mutateAsync(versPayload(envoyees, bareme))
      toast.success(`${envoyees.length} note(s) enregistrée(s).`)
    } catch (error) {
      // 422 : « notes.3.valeur » → erreur sur la 4e ligne envoyée
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const parLigne: Record<number, string> = {}
        for (const [champ, messages] of Object.entries<string[]>(error.response.data?.errors ?? {})) {
          const index = Number(champ.split('.')[1])
          if (envoyees[index]) parLigne[envoyees[index].eleve_id] = messages[0]
        }
        setErreursServeur(parLigne)
      }
      toast.error(getErrorMessage(error))
    }
  }, [aEnregistrer, nbErreurs, verrouillee, modifiees, enregistrement, bareme])

  // Ctrl+S / Cmd+S : enregistrer sans quitter le clavier
  useEffect(() => {
    const raccourci = (event: globalThis.KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault()
        enregistrer()
      }
    }
    window.addEventListener('keydown', raccourci)
    return () => window.removeEventListener('keydown', raccourci)
  }, [enregistrer])

  const surTouche = (index: number) => (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter' || event.key === 'ArrowDown') {
      event.preventDefault()
      focaliser(index + 1)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      focaliser(index - 1)
    } else if (event.key.toLowerCase() === 'a' && !event.ctrlKey && !event.metaKey) {
      // « A » bascule l'absence de l'élève puis passe au suivant
      event.preventDefault()
      modifier(index, { absent: !lignes[index].absent, texte: '' })
      focaliser(index + 1)
    }
  }

  const affectation = evaluation.affectation

  return (
    <>
      <Button variant="ghost" size="sm" asChild className="mb-2 -ml-2">
        <Link to={`/notes?affectation=${evaluation.affectation_id}&periode=${evaluation.periode_id}`}>
          <ArrowLeft />
          Évaluations
        </Link>
      </Button>

      <PageHeader
        title={evaluation.titre}
        description={`${affectation?.classe?.nom} · ${affectation?.matiere?.nom} · ${evaluation.type_libelle} du ${formatDate(evaluation.date)} · noté sur ${formatNote(bareme)}`}
        actions={
          !verrouillee && (
            <>
              <Button variant="outline" onClick={() => setLignes(lignesInitiales(eleves))} disabled={!aEnregistrer || enregistrement.isPending}>
                <RotateCcw />
                Annuler
              </Button>
              <Button onClick={enregistrer} disabled={!aEnregistrer || nbErreurs > 0 || enregistrement.isPending}>
                {enregistrement.isPending ? <Loader2 className="animate-spin" /> : <Save />}
                Enregistrer{aEnregistrer && ` (${modifiees.length})`}
              </Button>
            </>
          )
        }
      />

      {verrouillee && (
        <Alert className="mb-4">
          <Lock />
          <AlertDescription>
            La période « {evaluation.periode?.libelle} » est clôturée : les notes sont en lecture seule.
          </AlertDescription>
        </Alert>
      )}

      <div className="grid gap-4 lg:grid-cols-[1fr_16rem]">
        <div className="overflow-x-auto rounded-lg border bg-background">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">N°</TableHead>
                <TableHead>Élève</TableHead>
                <TableHead className="w-36">Note / {formatNote(bareme)}</TableHead>
                <TableHead className="w-20 text-center">Absent</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {eleves.map((eleve, index) => {
                const ligne = lignes[index]
                const erreur = erreurs[eleve.eleve_id] ?? erreursServeur[eleve.eleve_id]
                const modifiee = modifiees.some((m) => m.eleve_id === eleve.eleve_id)
                return (
                  <TableRow key={eleve.eleve_id} className={cn(modifiee && 'bg-primary/5', !eleve.inscrit && 'text-muted-foreground')}>
                    <TableCell className="tabular-nums text-muted-foreground">{index + 1}</TableCell>
                    <TableCell>
                      <label htmlFor={`note-${eleve.eleve_id}`} className="block">
                        <span className="font-medium">{eleve.nom.toUpperCase()}</span> {eleve.prenom}
                        {!eleve.inscrit && (
                          <Badge variant="outline" className="ml-2">
                            a quitté la classe
                          </Badge>
                        )}
                      </label>
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      <Input
                        id={`note-${eleve.eleve_id}`}
                        ref={(el) => {
                          champs.current[index] = el
                        }}
                        inputMode="decimal"
                        autoComplete="off"
                        value={ligne.absent ? '' : ligne.texte}
                        placeholder={ligne.absent ? 'Abs.' : '—'}
                        // Lecture seule plutôt que désactivé : le champ d'un absent reste atteignable
                        // au clavier (Entrée, flèches) et « A » permet d'annuler l'absence
                        readOnly={ligne.absent}
                        disabled={verrouillee}
                        aria-invalid={!!erreur}
                        aria-describedby={erreur ? `note-${eleve.eleve_id}-erreur` : undefined}
                        onChange={(event) => modifier(index, { texte: event.target.value })}
                        onKeyDown={surTouche(index)}
                        onFocus={(event) => event.target.select()}
                        className={cn('h-8 w-24 text-right tabular-nums', ligne.absent && 'bg-muted placeholder:text-foreground/70')}
                      />
                      {erreur && (
                        <p id={`note-${eleve.eleve_id}-erreur`} className="mt-1 text-xs text-destructive">
                          {erreur}
                        </p>
                      )}
                    </TableCell>
                    <TableCell className="text-center">
                      <input
                        type="checkbox"
                        aria-label={`${eleve.prenom} ${eleve.nom} absent`}
                        className="size-4 accent-primary"
                        checked={ligne.absent}
                        disabled={verrouillee}
                        onChange={(event) => modifier(index, { absent: event.target.checked, texte: '' })}
                      />
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </div>

        <aside className="space-y-4">
          <div className="rounded-lg border bg-background p-4">
            <h2 className="mb-3 text-sm font-medium">Statistiques</h2>
            <dl className="grid grid-cols-2 gap-3 text-sm">
              <Stat label="Saisies" valeur={`${stats.saisies} / ${eleves.length}`} />
              <Stat label="Absents" valeur={String(stats.absents)} />
              <Stat label="Moyenne" valeur={stats.moyenne === null ? '—' : formatNote(stats.moyenne)} />
              <Stat
                label="Min · Max"
                valeur={stats.min === null ? '—' : `${formatNote(stats.min)} · ${formatNote(stats.max!)}`}
              />
            </dl>
            {nbErreurs > 0 && <p className="mt-3 text-sm text-destructive">{nbErreurs} note(s) invalide(s) à corriger.</p>}
          </div>
          {!verrouillee && (
            <div className="rounded-lg border bg-background p-4 text-sm text-muted-foreground">
              <h2 className="mb-2 flex items-center gap-2 font-medium text-foreground">
                <Keyboard className="size-4" />
                Raccourcis
              </h2>
              <ul className="space-y-1">
                <li>
                  <kbd className="rounded border px-1">Entrée</kbd> / <kbd className="rounded border px-1">↓</kbd> élève suivant
                </li>
                <li>
                  <kbd className="rounded border px-1">↑</kbd> élève précédent
                </li>
                <li>
                  <kbd className="rounded border px-1">A</kbd> marquer absent
                </li>
                <li>
                  <kbd className="rounded border px-1">Ctrl</kbd> + <kbd className="rounded border px-1">S</kbd> enregistrer
                </li>
              </ul>
            </div>
          )}
        </aside>
      </div>

      <ConfirmDialog
        open={blocker.state === 'blocked'}
        onOpenChange={(ouvert) => !ouvert && blocker.state === 'blocked' && blocker.reset()}
        title="Quitter sans enregistrer ?"
        description={`${modifiees.length} note(s) modifiée(s) seront perdues.`}
        confirmLabel="Quitter"
        destructive
        onConfirm={async () => blocker.proceed?.()}
      />
    </>
  )
}

function Stat({ label, valeur }: { label: string; valeur: string }) {
  return (
    <div>
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="font-medium tabular-nums">{valeur}</dd>
    </div>
  )
}
