import { ClipboardPen, Lock, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { affectations } from '@/features/personnes/api'
import { useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { formatDate } from '@/lib/format'
import { evaluations } from '../api'
import { EvaluationFormDialog } from '../components/EvaluationFormDialog'
import { formatNote } from '../saisie'
import type { Evaluation } from '../types'

/** Espace professeur : ses classes / matières, puis les évaluations de la période choisie. */
export function EvaluationsProfPage() {
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const anneeActive = useAnneeActive()
  const mesAffectations = affectations.useList({ per_page: 100 })

  const periodes = anneeActive.data?.periodes ?? []
  // Date du jour figée au montage (pas d'appel impur pendant le rendu)
  const [aujourdhui] = useState(() => new Date().toISOString().slice(0, 10))
  const periodeCourante = periodes.find((p) => aujourdhui >= p.date_debut && aujourdhui <= p.date_fin) ?? periodes[0]

  // Sélection conservée dans l'URL (retour depuis la grille de saisie)
  const affectationId = Number(params.get('affectation')) || mesAffectations.data?.data[0]?.id
  const periodeId = Number(params.get('periode')) || periodeCourante?.id
  const affectation = mesAffectations.data?.data.find((a) => a.id === affectationId)
  const periode = periodes.find((p) => p.id === periodeId)

  const liste = evaluations.useList({ affectation_id: affectationId, periode_id: periodeId, per_page: 50 }, { enabled: !!affectationId && !!periodeId })
  const suppression = evaluations.useRemove()
  const formulaire = useDialogState<Evaluation>()
  const aSupprimer = useDialogState<Evaluation>()

  const choisir = (cle: 'affectation' | 'periode', valeur: string) => {
    const suivants = new URLSearchParams(params)
    suivants.set(cle, valeur)
    if (cle === 'affectation' && periodeId) suivants.set('periode', String(periodeId))
    setParams(suivants, { replace: true })
  }

  const ouvrirGrille = (evaluation: Evaluation) => navigate(`/notes/evaluations/${evaluation.id}`)

  const colonnes: Column<Evaluation>[] = [
    { header: 'Date', cell: (e) => formatDate(e.date), className: 'w-28' },
    {
      header: 'Évaluation',
      cell: (e) => (
        <button type="button" onClick={() => ouvrirGrille(e)} className="text-left font-medium hover:underline">
          {e.titre}
        </button>
      ),
    },
    {
      header: 'Type',
      cell: (e) => <Badge variant={e.type === 'composition' ? 'default' : 'secondary'}>{e.type_libelle}</Badge>,
      className: 'hidden sm:table-cell',
    },
    { header: 'Barème', cell: (e) => `/ ${formatNote(e.bareme)}`, className: 'hidden md:table-cell' },
    { header: 'Notes', cell: (e) => e.notes_count, className: 'text-right' },
    {
      header: 'Moyenne',
      cell: (e) => (e.moyenne === null || e.moyenne === undefined ? '—' : `${formatNote(e.moyenne)} / ${formatNote(e.bareme)}`),
      className: 'text-right',
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (e) => (
        <RowActions
          label={e.titre}
          actions={[
            { label: e.verrouillee ? 'Consulter les notes' : 'Saisir les notes', icon: ClipboardPen, onSelect: () => ouvrirGrille(e) },
            { label: 'Modifier', icon: Pencil, onSelect: () => formulaire.ouvrir(e), hidden: e.verrouillee },
            { label: 'Supprimer', icon: Trash2, onSelect: () => aSupprimer.ouvrir(e), destructive: true, hidden: e.verrouillee },
          ]}
        />
      ),
    },
  ]

  if (mesAffectations.data && mesAffectations.data.data.length === 0) {
    return (
      <>
        <PageHeader title="Notes" />
        <p className="text-sm text-muted-foreground">Vous n’avez aucune affectation cette année. Contactez la direction.</p>
      </>
    )
  }

  return (
    <>
      <PageHeader
        title="Notes"
        description="Créez vos évaluations puis saisissez les notes de la classe."
        actions={
          periode && !periode.cloturee && affectation && (
            <Button onClick={() => formulaire.ouvrir()}>
              <Plus />
              Nouvelle évaluation
            </Button>
          )
        }
      />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="space-y-1.5">
          <Label htmlFor="choix-affectation">Classe et matière</Label>
          <Select value={affectationId ? String(affectationId) : ''} onValueChange={(v) => choisir('affectation', v)}>
            <SelectTrigger id="choix-affectation" className="w-full sm:w-72">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {mesAffectations.data?.data.map((a) => (
                <SelectItem key={a.id} value={String(a.id)}>
                  {a.classe?.nom} — {a.matiere?.nom}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="choix-periode">Période</Label>
          <Select value={periodeId ? String(periodeId) : ''} onValueChange={(v) => choisir('periode', v)}>
            <SelectTrigger id="choix-periode" className="w-full sm:w-48">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {periodes.map((p) => (
                <SelectItem key={p.id} value={String(p.id)}>
                  {p.libelle}
                  {p.cloturee && ' (clôturée)'}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {periode?.cloturee && (
          <Badge variant="secondary" className="h-9 self-start sm:self-end">
            <Lock />
            Période clôturée : lecture seule
          </Badge>
        )}
      </div>

      <DataTable
        columns={colonnes}
        data={liste.data?.data}
        isLoading={liste.isPending && !!affectationId}
        isFetching={liste.isFetching}
        emptyMessage="Aucune évaluation sur cette période"
        emptyDescription={periode?.cloturee ? 'La période est clôturée.' : 'Créez un devoir, une interrogation ou la composition, puis saisissez les notes.'}
        emptyIcon={ClipboardPen}
        emptyAction={
          periode && !periode.cloturee && affectation && (
            <Button size="sm" onClick={() => formulaire.ouvrir()}>
              <Plus />
              Nouvelle évaluation
            </Button>
          )
        }
      />

      {affectation && periode && (
        <EvaluationFormDialog
          open={formulaire.open}
          onOpenChange={formulaire.setOpen}
          affectationId={affectation.id}
          periode={periode}
          evaluation={formulaire.item}
          onSaved={(e) => !formulaire.item && ouvrirGrille(e)}
        />
      )}
      <ConfirmDialog
        open={aSupprimer.open}
        onOpenChange={aSupprimer.setOpen}
        title={`Supprimer « ${aSupprimer.item?.titre} » ?`}
        description={`Les ${aSupprimer.item?.notes_count ?? 0} note(s) saisie(s) seront supprimées.`}
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer.item!.id)
          toast.success('Évaluation supprimée.')
        }}
      />
    </>
  )
}
