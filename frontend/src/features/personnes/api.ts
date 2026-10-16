import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { createResource } from '@/lib/resource'
import { classes } from '@/features/structure/api'
import type { CompteValues, EleveValues, EnseignantValues, TuteurValues } from './schemas'
import type {
  Affectation,
  AnalyseImport,
  Eleve,
  Enseignant,
  Inscription,
  LienParente,
  ResultatImport,
  StatutInscription,
  Tuteur,
} from './types'

type ElevePayload = EleveValues & { tuteurs?: { tuteur_id: number; lien: LienParente }[] }

export const eleves = createResource<Eleve, ElevePayload>('eleves', '/eleves')
export const tuteurs = createResource<Tuteur, TuteurValues>('tuteurs', '/tuteurs')
export const enseignants = createResource<Enseignant, EnseignantValues>('enseignants', '/enseignants')
export const affectations = createResource<Affectation, { enseignant_id: number; matiere_id: number; classe_id: number }>(
  'affectations',
  '/affectations',
)
export const inscriptions = createResource<
  Inscription,
  { eleve_id?: number; classe_id?: number; statut?: StatutInscription }
>('inscriptions', '/inscriptions')

/** Après une (dés)inscription : effectifs des classes, fiches élèves et listes à rafraîchir. */
function useInvaliderScolarite() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: inscriptions.keys.all }),
      queryClient.invalidateQueries({ queryKey: eleves.keys.all }),
      queryClient.invalidateQueries({ queryKey: classes.keys.all }),
    ])
}

export function useEnregistrerInscription() {
  const invalider = useInvaliderScolarite()
  return useMutation({
    mutationFn: ({ id, donnees }: { id?: number; donnees: { eleve_id?: number; classe_id?: number; statut?: StatutInscription } }) =>
      id ? inscriptions.endpoints.update(id, donnees) : inscriptions.endpoints.create(donnees),
    onSuccess: invalider,
  })
}

export function usePhotoEleve(eleveId: number) {
  const queryClient = useQueryClient()
  const rafraichir = () => queryClient.invalidateQueries({ queryKey: eleves.keys.all })

  const envoyer = useMutation({
    mutationFn: async (fichier: File) => {
      const formulaire = new FormData()
      formulaire.append('photo', fichier)
      return (await api.post<{ data: Eleve }>(`/eleves/${eleveId}/photo`, formulaire)).data.data
    },
    onSuccess: rafraichir,
  })

  const supprimer = useMutation({
    mutationFn: async () => (await api.delete<{ data: Eleve }>(`/eleves/${eleveId}/photo`)).data.data,
    onSuccess: rafraichir,
  })

  return { envoyer, supprimer }
}

/** Création du compte de connexion d'un élève ou d'un parent. */
export function useCreerCompte(type: 'eleves' | 'tuteurs', id: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (valeurs: CompteValues) => (await api.post(`/${type}/${id}/compte`, valeurs)).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: [type] }),
  })
}

export function useMesEnfants() {
  return useQuery({
    queryKey: ['mes-enfants'],
    queryFn: async () => (await api.get<{ data: Eleve[] }>('/moi/enfants')).data.data,
  })
}

// --- Import Excel -----------------------------------------------------------

function formulaireFichier(fichier: File): FormData {
  const formulaire = new FormData()
  formulaire.append('fichier', fichier)
  return formulaire
}

export function useAnalyserImport() {
  return useMutation({
    mutationFn: async (fichier: File) =>
      (await api.post<{ data: AnalyseImport }>('/eleves/import/analyse', formulaireFichier(fichier))).data.data,
  })
}

export function useImporterEleves() {
  const invalider = useInvaliderScolarite()
  return useMutation({
    mutationFn: async (fichier: File) =>
      (await api.post<{ data: ResultatImport }>('/eleves/import', formulaireFichier(fichier))).data.data,
    onSuccess: invalider,
  })
}

/** Téléchargement authentifié du modèle (le token ne peut pas passer dans un simple lien). */
export async function telechargerModeleImport(): Promise<void> {
  const { data } = await api.get<Blob>('/eleves/import/modele', { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const lien = document.createElement('a')
  lien.href = url
  lien.download = 'modele-import-eleves.xlsx'
  lien.click()
  URL.revokeObjectURL(url)
}
