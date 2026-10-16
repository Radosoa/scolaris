import { Calculator, CheckCircle2, Circle, Eye, FileDown, FileStack, Loader2, Lock, MessageSquareText, ShieldCheck } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { classes, useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { getErrorMessage } from '@/lib/api'
import { formatNombre } from '@/lib/format'
import { cn } from '@/lib/utils'
import { telechargerPdfBulletin, telechargerPdfClasse, useActionClasse, useBulletinsClasse, useGenererPdfClasse } from '../api'
import { AppreciationGeneraleDialog } from '../components/AppreciationGeneraleDialog'
import { BoutonPdf } from '../components/BoutonPdf'
import { Moyenne } from '../components/Moyenne'
import { formatHeures, formatMoyenne, formatRang, rangsExAequo, tauxReussite } from '../format'
import type { Bulletin, EtatClasse } from '../types'

const ETATS: Record<EtatClasse['etat'], { libelle: string; variante: 'outline' | 'secondary' | 'default' }> = {
  non_calcule: { libelle: 'Non calculés', variante: 'outline' },
  brouillon: { libelle: 'Brouillon', variante: 'secondary' },
  valide: { libelle: 'Validés', variante: 'default' },
}

/** Direction : classe + période → calculer → prévisualiser → valider → générer les PDF. */
export function BulletinsClassePage() {
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })

  const periodes = anneeActive.data?.periodes ?? []
  // Par défaut : la dernière période clôturée (celle dont on édite les bulletins)
  const periodeParDefaut = [...periodes].reverse().find((p) => p.cloturee) ?? periodes[0]
  const classeId = Number(params.get('classe')) || listeClasses.data?.data[0]?.id
  const periodeId = Number(params.get('periode')) || periodeParDefaut?.id

  const requete = useBulletinsClasse(classeId, periodeId)
  const action = useActionClasse(classeId ?? 0, periodeId ?? 0)
  const pdf = useGenererPdfClasse(classeId ?? 0, periodeId ?? 0)
  const appreciation = useDialogState<Bulletin>()
  const [confirmerValidation, setConfirmerValidation] = useState(false)

  const choisir = (cle: 'classe' | 'periode', valeur: string) => {
    const suivants = new URLSearchParams(params)
    suivants.set(cle, valeur)
    setParams(suivants, { replace: true })
  }

  const bulletins = requete.data?.data
  const meta = requete.data?.meta
  const exAequo = rangsExAequo(bulletins ?? [])
  const reussite = tauxReussite(bulletins ?? [])
  const premier = bulletins?.[0]
  const modifiable = meta?.etat !== 'valide'

  const calculer = async () => {
    try {
      const { meta } = await action.mutateAsync('calculer')
      toast.success(`${meta.nombre} bulletin(s) calculé(s).`)
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  const genererPdf = async () => {
    try {
      await pdf.mutateAsync()
      toast.success('Génération des PDF lancée.')
    } catch (error) {
      toast.error(getErrorMessage(error))
    }
  }

  const colonnes: Column<Bulletin>[] = [
    {
      header: 'Rang',
      className: 'w-24',
      cell: (b) => (
        <span className={cn('tabular-nums', b.rang === null && 'text-xs text-muted-foreground')}>
          {formatRang(b.rang, b.rang !== null && exAequo.has(b.rang))}
        </span>
      ),
    },
    {
      header: 'Élève',
      cell: (b) => (
        <Link to={`/bulletins/${b.id}`} className="font-medium hover:underline">
          {b.eleve?.nom} {b.eleve?.prenom}
        </Link>
      ),
    },
    { header: 'Moyenne', className: 'text-right', cell: (b) => <Moyenne valeur={b.moyenne} /> },
    {
      header: 'Mention',
      className: 'hidden md:table-cell',
      cell: (b) => (b.mention ? <Badge variant="secondary">{b.mention}</Badge> : '—'),
    },
    {
      header: 'Abs. non just.',
      className: 'hidden text-right lg:table-cell',
      cell: (b) => <span className="tabular-nums">{formatHeures(b.absences.heures_non_justifiees)}</span>,
    },
    {
      header: 'Appréciation générale',
      className: 'hidden max-w-72 xl:table-cell',
      cell: (b) =>
        b.appreciation_generale ? (
          <span className="line-clamp-1 text-sm whitespace-normal">{b.appreciation_generale}</span>
        ) : (
          <span className="text-xs text-muted-foreground">À saisir</span>
        ),
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (b) => (
        <RowActions
          label={`${b.eleve?.prenom} ${b.eleve?.nom}`}
          actions={[
            { label: 'Aperçu', icon: Eye, onSelect: () => navigate(`/bulletins/${b.id}`) },
            { label: 'Appréciation générale', icon: MessageSquareText, onSelect: () => appreciation.ouvrir(b), hidden: !modifiable },
            {
              label: b.valide ? 'Télécharger le PDF' : 'PDF (projet)',
              icon: FileDown,
              onSelect: () => telechargerPdfBulletin(b.id).catch((error) => toast.error(getErrorMessage(error))),
            },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <PageHeader title="Bulletins" description="Calculez, vérifiez puis validez les bulletins d’une classe." />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="space-y-1.5">
          <Label htmlFor="choix-classe">Classe</Label>
          <Select value={classeId ? String(classeId) : ''} onValueChange={(v) => choisir('classe', v)}>
            <SelectTrigger id="choix-classe" className="w-full sm:w-48">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {listeClasses.data?.data.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.nom}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="choix-periode">Période</Label>
          <Select value={periodeId ? String(periodeId) : ''} onValueChange={(v) => choisir('periode', v)}>
            <SelectTrigger id="choix-periode" className="w-full sm:w-56">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {periodes.map((p) => (
                <SelectItem key={p.id} value={String(p.id)}>
                  {p.libelle} {p.cloturee ? '(clôturée)' : '(en cours)'}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {meta && (
          <Badge variant={ETATS[meta.etat].variante} className="h-9 self-start px-3 sm:self-end">
            {meta.etat === 'valide' && <Lock />}
            {ETATS[meta.etat].libelle}
          </Badge>
        )}
      </div>

      {meta && !meta.periode.cloturee && (
        <Alert className="mb-4">
          <Lock />
          <AlertTitle>{meta.periode.libelle} n’est pas clôturé(e)</AlertTitle>
          <AlertDescription>
            <p>
              Les bulletins se calculent sur une période clôturée, quand plus aucune note ne peut changer.{' '}
              {anneeActive.data && (
                <Link to={`/structure/annees/${anneeActive.data.id}`} className="font-medium underline">
                  Gérer les périodes
                </Link>
              )}
            </p>
          </AlertDescription>
        </Alert>
      )}

      {meta && (
        <div className="mb-4 grid gap-3 lg:grid-cols-3">
          <Etape
            numero={1}
            titre="Calculer"
            fait={meta.etat !== 'non_calcule'}
            detail={
              meta.calcule_le
                ? `Dernier calcul le ${new Date(meta.calcule_le).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}`
                : 'Moyennes, rangs, mentions et absences.'
            }
          >
            <Button
              size="sm"
              variant={meta.etat === 'non_calcule' ? 'default' : 'outline'}
              onClick={calculer}
              disabled={!meta.periode.cloturee || !modifiable || action.isPending}
            >
              {action.isPending && action.variables === 'calculer' ? <Loader2 className="animate-spin" /> : <Calculator />}
              {meta.etat === 'non_calcule' ? 'Calculer' : 'Recalculer'}
            </Button>
          </Etape>
          <Etape
            numero={2}
            titre="Valider"
            fait={meta.etat === 'valide'}
            detail={
              meta.valide_le
                ? `Validés le ${new Date(meta.valide_le).toLocaleDateString('fr-FR')} : visibles par les élèves et les parents.`
                : 'Après vérification et saisie des appréciations.'
            }
          >
            <Button size="sm" onClick={() => setConfirmerValidation(true)} disabled={meta.etat !== 'brouillon' || !meta.periode.cloturee}>
              <ShieldCheck />
              Valider
            </Button>
          </Etape>
          <Etape
            numero={3}
            titre="Générer les PDF"
            fait={meta.pdf_classe && !meta.pdf_en_cours}
            detail={
              meta.pdf_en_cours
                ? `Génération en cours… ${meta.pdf_generes} / ${meta.nombre}`
                : meta.pdf_classe
                  ? 'PDF individuels et PDF de la classe prêts.'
                  : 'Un PDF par élève et un PDF regroupé pour l’impression.'
            }
          >
            {meta.pdf_classe && !meta.pdf_en_cours && (
              <BoutonPdf libelle="Classe" telecharger={() => telechargerPdfClasse(meta.classe.id, meta.periode.id)} />
            )}
            <Button
              size="sm"
              variant={meta.pdf_classe ? 'outline' : 'default'}
              onClick={genererPdf}
              disabled={meta.etat !== 'valide' || meta.pdf_en_cours || pdf.isPending}
            >
              {meta.pdf_en_cours || pdf.isPending ? <Loader2 className="animate-spin" /> : <FileStack />}
              {meta.pdf_classe ? 'Régénérer' : 'Générer'}
            </Button>
          </Etape>
        </div>
      )}

      {premier && (
        <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
          <Chiffre libelle="Moyenne de la classe" valeur={formatMoyenne(premier.moyenne_classe)} />
          <Chiffre libelle="Plus forte moyenne" valeur={formatMoyenne(premier.moyenne_max)} />
          <Chiffre libelle="Plus faible moyenne" valeur={formatMoyenne(premier.moyenne_min)} />
          <Chiffre libelle="Moyenne ≥ 10" valeur={reussite === null ? '—' : `${formatNombre(Math.round(reussite * 100))} %`} />
        </div>
      )}

      <DataTable
        columns={colonnes}
        data={bulletins}
        isLoading={requete.isPending && !!classeId}
        isFetching={requete.isFetching && !meta?.pdf_en_cours}
        emptyMessage={
          meta?.periode.cloturee ? 'Bulletins non calculés : cliquez sur « Calculer ».' : 'Aucun bulletin pour cette période.'
        }
      />

      <AppreciationGeneraleDialog open={appreciation.open} onOpenChange={appreciation.setOpen} bulletin={appreciation.item} />
      <ConfirmDialog
        open={confirmerValidation}
        onOpenChange={setConfirmerValidation}
        title={`Valider les ${meta?.nombre ?? ''} bulletins de ${meta?.classe.nom ?? ''} ?`}
        description="Ils deviennent visibles par les élèves et les parents et ne pourront plus être modifiés ni recalculés. La période ne pourra plus être rouverte."
        confirmLabel="Valider définitivement"
        onConfirm={async () => {
          await action.mutateAsync('valider')
          toast.success('Bulletins validés.')
        }}
      />
    </>
  )
}

function Etape({ numero, titre, fait, detail, children }: { numero: number; titre: string; fait: boolean; detail: string; children: ReactNode }) {
  return (
    <Card size="sm">
      <CardContent className="flex h-full items-start gap-3">
        {fait ? <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" /> : <Circle className="mt-0.5 size-5 shrink-0 text-muted-foreground" />}
        <div className="flex h-full min-w-0 flex-1 flex-col gap-3">
          <div>
            <p className="text-sm font-medium">
              {numero}. {titre}
            </p>
            <p className="text-xs text-muted-foreground">{detail}</p>
          </div>
          <div className="mt-auto flex flex-wrap gap-2">{children}</div>
        </div>
      </CardContent>
    </Card>
  )
}

function Chiffre({ libelle, valeur }: { libelle: string; valeur: string }) {
  return (
    <Card size="sm">
      <CardHeader>
        <CardDescription>{libelle}</CardDescription>
        <p className="text-2xl font-semibold tabular-nums">{valeur}</p>
      </CardHeader>
    </Card>
  )
}
