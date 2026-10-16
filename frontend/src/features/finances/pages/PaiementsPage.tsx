import { Ban, FileDown, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { DataTable, type Column } from '@/components/data-table/DataTable'
import { SearchInput } from '@/components/data-table/SearchInput'
import { useListState } from '@/components/data-table/use-list-state'
import { PageHeader } from '@/components/PageHeader'
import { RowActions } from '@/components/RowActions'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAuth } from '@/features/auth/auth-context'
import { useMesEnfants } from '@/features/personnes/api'
import { classes, useAnneeActive } from '@/features/structure/api'
import { useDialogState } from '@/hooks/use-dialog-state'
import { getErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/utils'
import { telechargerRecu, useImpayes, useJournal } from '../api'
import { AnnulationDialog } from '../components/AnnulationDialog'
import { FraisConfig } from '../components/FraisConfig'
import { SituationFinanciere } from '../components/SituationFinanciere'
import { formatEuros } from '../format'
import { MODES, type Paiement, type Solde } from '../types'

const TOUS = 'tous'

/** Secrétariat et direction : impayés, journal, frais ; parent : situation de ses enfants. */
export function PaiementsPage() {
  const { user } = useAuth()
  return user?.permissions.includes('paiements.gerer') ? <GestionPaiements /> : <PaiementsEnfants />
}

function GestionPaiements() {
  const { user } = useAuth()
  const [params, setParams] = useSearchParams()
  const onglet = params.get('onglet') ?? 'impayes'

  return (
    <>
      <PageHeader title="Paiements" description="Suivi des frais de scolarité, encaissements par tranches et reçus." />
      <Tabs value={onglet} onValueChange={(v) => setParams({ onglet: v }, { replace: true })} className="mb-4">
        <TabsList>
          <TabsTrigger value="impayes">Impayés</TabsTrigger>
          <TabsTrigger value="journal">Journal des paiements</TabsTrigger>
          <TabsTrigger value="frais">Frais et échéancier</TabsTrigger>
        </TabsList>
      </Tabs>
      {onglet === 'impayes' && <Impayes />}
      {onglet === 'journal' && <Journal />}
      {onglet === 'frais' && <FraisConfig modifiable={user?.permissions.includes('structure.gerer') ?? false} />}
    </>
  )
}

function useClasses() {
  const anneeActive = useAnneeActive()
  return classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })
}

function Impayes() {
  const navigate = useNavigate()
  const liste = useListState(20)
  const [statut, setStatut] = useState('en_retard')
  const [classeId, setClasseId] = useState(TOUS)
  const listeClasses = useClasses()
  const requete = useImpayes({ ...liste.params, statut, classe_id: classeId === TOUS ? undefined : classeId })
  const totaux = requete.data?.meta.totaux

  const filtrer = (appliquer: () => void) => {
    appliquer()
    liste.setPage(1)
  }

  const colonnes: Column<Solde & { id: number }>[] = [
    {
      header: 'Élève',
      cell: (s) => (
        <Link to={`/paiements/eleves/${s.eleve.id}`} className="font-medium hover:underline">
          {s.eleve.nom} {s.eleve.prenom}
        </Link>
      ),
    },
    { header: 'Classe', className: 'hidden sm:table-cell', cell: (s) => s.classe.nom },
    { header: 'Frais', className: 'hidden text-right md:table-cell', cell: (s) => <span className="tabular-nums">{formatEuros(s.total)}</span> },
    { header: 'Réglé', className: 'hidden text-right md:table-cell', cell: (s) => <span className="tabular-nums">{formatEuros(s.paye)}</span> },
    { header: 'Reste', className: 'text-right', cell: (s) => <span className="tabular-nums">{formatEuros(s.reste)}</span> },
    {
      header: 'En retard',
      className: 'text-right',
      cell: (s) => <span className={cn('tabular-nums', s.retard > 0 ? 'font-medium text-destructive' : 'text-muted-foreground')}>{formatEuros(s.retard)}</span>,
    },
    {
      header: 'Actions',
      className: 'w-12 text-right',
      cell: (s) => (
        <RowActions
          label={`${s.eleve.prenom} ${s.eleve.nom}`}
          actions={[{ label: 'Situation et encaissement', icon: Wallet, onSelect: () => navigate(`/paiements/eleves/${s.eleve.id}`) }]}
        />
      ),
    },
  ]

  return (
    <>
      {totaux ? (
        <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Chiffre libelle="Attendu sur l’année" valeur={formatEuros(totaux.attendu)} detail={`${totaux.eleves} élèves`} />
          <Chiffre libelle="Encaissé" valeur={formatEuros(totaux.encaisse)} detail={totaux.attendu ? `${Math.round((totaux.encaisse / totaux.attendu) * 100)} %` : undefined} />
          <Chiffre libelle="Reste à percevoir" valeur={formatEuros(totaux.reste)} />
          <Chiffre libelle="En retard" valeur={formatEuros(totaux.retard)} detail={`${totaux.en_retard} famille(s)`} alerte={totaux.retard > 0} />
        </div>
      ) : (
        <Skeleton className="mb-4 h-24 w-full" />
      )}

      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <Tabs value={statut} onValueChange={(v) => filtrer(() => setStatut(v))}>
          <TabsList>
            <TabsTrigger value="en_retard">En retard</TabsTrigger>
            <TabsTrigger value="reste">Reste à payer</TabsTrigger>
            <TabsTrigger value="solde">À jour</TabsTrigger>
            <TabsTrigger value={TOUS}>Tous</TabsTrigger>
          </TabsList>
        </Tabs>
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Élève ou matricule…" />
        <SelectClasse valeur={classeId} onChange={(v) => filtrer(() => setClasseId(v))} options={listeClasses.data?.data} />
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data.map((s) => ({ ...s, id: s.inscription_id }))}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage={statut === 'en_retard' ? 'Aucune famille en retard de paiement.' : 'Aucun élève.'}
      />
    </>
  )
}

function Journal() {
  const liste = useListState(20)
  const [du, setDu] = useState('')
  const [au, setAu] = useState('')
  const [mode, setMode] = useState(TOUS)
  const annulation = useDialogState<Paiement>()
  const requete = useJournal({ ...liste.params, du: du || undefined, au: au || undefined, mode: mode === TOUS ? undefined : mode })

  const filtrer = (appliquer: () => void) => {
    appliquer()
    liste.setPage(1)
  }

  const colonnes: Column<Paiement>[] = [
    { header: 'Date', className: 'w-28', cell: (p) => formatDate(p.date) },
    { header: 'Reçu', cell: (p) => <span className={cn('font-mono text-xs', p.annule && 'line-through')}>{p.numero_recu}</span> },
    {
      header: 'Élève',
      cell: (p) => (
        <Link to={`/paiements/eleves/${p.eleve?.id}`} className="font-medium hover:underline">
          {p.eleve?.nom} {p.eleve?.prenom}
        </Link>
      ),
    },
    { header: 'Classe', className: 'hidden lg:table-cell', cell: (p) => p.classe?.nom },
    { header: 'Mode', className: 'hidden md:table-cell', cell: (p) => p.mode_libelle },
    {
      header: 'Montant',
      className: 'text-right',
      cell: (p) =>
        p.annule ? (
          <span title={p.motif_annulation ?? undefined}>
            <Badge variant="destructive" className="mr-2">
              Annulé
            </Badge>
            <span className="text-muted-foreground tabular-nums line-through">{formatEuros(p.montant)}</span>
          </span>
        ) : (
          <span className="font-medium tabular-nums">{formatEuros(p.montant)}</span>
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
            { label: 'Annuler le paiement', icon: Ban, onSelect: () => annulation.ouvrir(p), hidden: p.annule, destructive: true },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <SearchInput value={liste.recherche} onChange={liste.setRecherche} placeholder="Élève ou n° de reçu…" />
        <div className="flex items-center gap-2">
          <Input type="date" aria-label="Du" value={du} onChange={(e) => filtrer(() => setDu(e.target.value))} className="w-40" />
          <span className="text-sm text-muted-foreground">au</span>
          <Input type="date" aria-label="Au" value={au} onChange={(e) => filtrer(() => setAu(e.target.value))} className="w-40" />
        </div>
        <Select value={mode} onValueChange={(v) => filtrer(() => setMode(v))}>
          <SelectTrigger className="w-full sm:w-44" aria-label="Mode de paiement">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TOUS}>Tous les modes</SelectItem>
            {Object.entries(MODES).map(([valeur, libelle]) => (
              <SelectItem key={valeur} value={valeur}>
                {libelle}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {requete.data && (
          <Badge variant="secondary" className="h-8 px-3 lg:ml-auto">
            Encaissé : {formatEuros(requete.data.meta.total_encaisse)}
          </Badge>
        )}
      </div>

      <DataTable
        columns={colonnes}
        data={requete.data?.data}
        isLoading={requete.isPending}
        isFetching={requete.isFetching}
        meta={requete.data?.meta}
        onPageChange={liste.setPage}
        emptyMessage="Aucun paiement sur cette période."
      />
      <AnnulationDialog open={annulation.open} onOpenChange={annulation.setOpen} paiement={annulation.item} />
    </>
  )
}

function SelectClasse({ valeur, onChange, options }: { valeur: string; onChange: (v: string) => void; options?: { id: number; nom: string }[] }) {
  return (
    <Select value={valeur} onValueChange={onChange}>
      <SelectTrigger className="w-full sm:w-40" aria-label="Classe">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={TOUS}>Toutes les classes</SelectItem>
        {options?.map((c) => (
          <SelectItem key={c.id} value={String(c.id)}>
            {c.nom}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

function Chiffre({ libelle, valeur, detail, alerte }: { libelle: string; valeur: string; detail?: string; alerte?: boolean }) {
  return (
    <Card size="sm">
      <CardHeader>
        <CardDescription>{libelle}</CardDescription>
        <p className={cn('text-xl font-semibold tabular-nums', alerte && 'text-destructive')}>{valeur}</p>
        {detail && <p className="text-xs text-muted-foreground">{detail}</p>}
      </CardHeader>
    </Card>
  )
}

function PaiementsEnfants() {
  const enfants = useMesEnfants()

  return (
    <>
      <PageHeader title="Paiements" description="Frais de scolarité de vos enfants, échéancier et reçus. Pour régler, contactez le secrétariat." />
      {enfants.isPending && <Skeleton className="h-64 w-full" />}
      {enfants.data?.length === 0 && <p className="text-sm text-muted-foreground">Aucun enfant n’est rattaché à votre compte.</p>}
      <div className="space-y-6">
        {enfants.data?.map((enfant) => (
          <Card key={enfant.id}>
            <CardHeader>
              <CardTitle>{enfant.nom_complet}</CardTitle>
            </CardHeader>
            <CardContent>
              <SituationFinanciere eleveId={enfant.id} gestion={false} />
            </CardContent>
          </Card>
        ))}
      </div>
    </>
  )
}
