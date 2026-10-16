import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PageHeader } from '@/components/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useAuth } from '@/features/auth/auth-context'
import { affectations, enseignants } from '@/features/personnes/api'
import type { Affectation } from '@/features/personnes/types'
import { classes, useAnneeActive } from '@/features/structure/api'
import { aujourdhuiIso, formatNombre } from '@/lib/format'
import { ErreurChargement } from '@/pages/ErrorPages'
import { useEmploiDuTemps, useSupprimerCreneau } from '../api'
import { CreneauFormDialog } from '../components/CreneauFormDialog'
import { GrilleEmploiDuTemps } from '../components/GrilleEmploiDuTemps'
import { derniereOccurrence, totalHeures } from '../emploi-du-temps'
import type { Creneau, Jour } from '../types'

/**
 * Emploi du temps : construction par la direction (par classe ou par professeur),
 * consultation par le professeur (le sien) et l'élève (celui de sa classe).
 */
export function EmploiDuTempsPage() {
  const { user } = useAuth()
  const gestion = user?.permissions.includes('emploi-du-temps.gerer') ?? false

  return gestion ? <EmploiDuTempsDirection /> : <EmploiDuTempsPersonnel />
}

function EmploiDuTempsPersonnel() {
  const navigate = useNavigate()
  const { hasRole } = useAuth()
  const professeur = hasRole('professeur')
  const edt = useEmploiDuTemps({})
  const [aujourdhui] = useState(aujourdhuiIso)

  if (edt.isError) return <ErreurChargement error={edt.error} />

  return (
    <>
      <PageHeader
        title="Emploi du temps"
        description={
          edt.data
            ? `${professeur ? 'Vos cours' : `Classe de ${edt.data.meta.libelle}`} · ${formatNombre(totalHeures(edt.data.data))} h par semaine${professeur ? ' · cliquez sur un cours pour faire l’appel' : ''}`
            : undefined
        }
      />
      {edt.data ? (
        <GrilleEmploiDuTemps
          creneaux={edt.data.data}
          afficher={professeur ? 'classe' : 'enseignant'}
          onCreneau={professeur ? (c) => navigate(`/appel?date=${derniereOccurrence(c.jour, aujourdhui)}&creneau=${c.id}`) : undefined}
        />
      ) : (
        <Skeleton className="h-[36rem] w-full" />
      )}
    </>
  )
}

function EmploiDuTempsDirection() {
  const [params, setParams] = useSearchParams()
  const anneeActive = useAnneeActive()
  const listeClasses = classes.useList({ annee_scolaire_id: anneeActive.data?.id, per_page: 100 }, { enabled: !!anneeActive.data })
  const listeEnseignants = enseignants.useList({ per_page: 100 })

  const vue = params.get('vue') === 'enseignant' ? 'enseignant' : 'classe'
  const options = vue === 'classe' ? listeClasses.data?.data : listeEnseignants.data?.data
  const id = Number(params.get('id')) || options?.[0]?.id
  const filtre = vue === 'classe' ? { classe_id: id } : { enseignant_id: id }

  const edt = useEmploiDuTemps(filtre, { enabled: !!id })
  const coursPossibles = affectations.useList({ ...filtre, per_page: 100 }, { enabled: !!id })
  const suppression = useSupprimerCreneau()

  const [formulaire, setFormulaire] = useState<{ open: boolean; creneau: Creneau | null; depart?: { jour: Jour; heure: number } }>({
    open: false,
    creneau: null,
  })
  const [aSupprimer, setASupprimer] = useState<Creneau | null>(null)

  const changer = (suivants: Record<string, string>) => setParams(new URLSearchParams(suivants), { replace: true })
  const libelle = (a: Affectation) =>
    vue === 'classe' ? `${a.matiere?.nom} — ${a.enseignant?.nom_complet}` : `${a.classe?.nom} — ${a.matiere?.nom}`

  return (
    <>
      <PageHeader title="Emploi du temps" description="Cliquez sur une heure libre pour ajouter un cours, sur un cours pour le modifier." />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <Tabs value={vue} onValueChange={(v) => changer({ vue: v })}>
          <TabsList>
            <TabsTrigger value="classe">Par classe</TabsTrigger>
            <TabsTrigger value="enseignant">Par professeur</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="space-y-1.5">
          <Label htmlFor="choix-edt" className="sr-only">
            {vue === 'classe' ? 'Classe' : 'Professeur'}
          </Label>
          <Select value={id ? String(id) : ''} onValueChange={(v) => changer({ vue, id: v })}>
            <SelectTrigger id="choix-edt" className="w-full sm:w-64">
              <SelectValue placeholder="Choisir…" />
            </SelectTrigger>
            <SelectContent>
              {options?.map((o) => (
                <SelectItem key={o.id} value={String(o.id)}>
                  {'nom_complet' in o ? o.nom_complet : o.nom}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {edt.data && (
          <Badge variant="secondary" className="h-9 self-start px-3 sm:self-end">
            {formatNombre(totalHeures(edt.data.data))} h / semaine · {edt.data.data.length} cours
          </Badge>
        )}
      </div>

      {edt.isError && <ErreurChargement error={edt.error} />}
      {edt.data ? (
        <GrilleEmploiDuTemps
          creneaux={edt.data.data}
          afficher={vue === 'classe' ? 'enseignant' : 'classe'}
          onCreneau={(creneau) => setFormulaire({ open: true, creneau })}
          onAjouter={(jour, heure) => setFormulaire({ open: true, creneau: null, depart: { jour, heure } })}
        />
      ) : (
        <Skeleton className="h-[36rem] w-full" />
      )}

      <CreneauFormDialog
        open={formulaire.open}
        onOpenChange={(open) => setFormulaire((f) => ({ ...f, open }))}
        creneau={formulaire.creneau}
        depart={formulaire.depart}
        affectations={coursPossibles.isPlaceholderData ? [] : (coursPossibles.data?.data ?? [])}
        libelle={libelle}
        onSupprimer={(c) => {
          setFormulaire((f) => ({ ...f, open: false }))
          setASupprimer(c)
        }}
      />
      <ConfirmDialog
        open={!!aSupprimer}
        onOpenChange={(open) => !open && setASupprimer(null)}
        title="Supprimer ce créneau ?"
        description={`${aSupprimer?.matiere?.nom} en ${aSupprimer?.classe?.nom}, ${aSupprimer?.jour_libelle.toLowerCase()} de ${aSupprimer?.heure_debut} à ${aSupprimer?.heure_fin}. Les appels déjà faits sur ce créneau seront supprimés ; les absences restent enregistrées.`}
        confirmLabel="Supprimer"
        destructive
        onConfirm={async () => {
          await suppression.mutateAsync(aSupprimer!.id)
          toast.success('Créneau supprimé.')
        }}
      />
    </>
  )
}
