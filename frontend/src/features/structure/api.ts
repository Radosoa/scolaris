import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { api } from '@/lib/api'
import { createResource } from '@/lib/resource'
import type { AnneeValues, ClasseValues, MatiereValues, NiveauValues, PeriodeValues } from './schemas'
import type { AnneeScolaire, Classe, LigneCoefficient, Matiere, Niveau, Periode } from './types'

export const annees = createResource<AnneeScolaire, Partial<AnneeValues>>('annees-scolaires', '/annees-scolaires')
export const periodes = createResource<Periode, PeriodeValues & { annee_scolaire_id?: number }>('periodes', '/periodes')
export const niveaux = createResource<Niveau, NiveauValues>('niveaux', '/niveaux')
export const classes = createResource<Classe, ClasseValues>('classes', '/classes')
export const matieres = createResource<Matiere, MatiereValues>('matieres', '/matieres')

/** Année active, ou null si aucune n'est configurée (404). */
export function useAnneeActive(options: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: ['annee-active'],
    enabled: options.enabled,
    queryFn: async () => {
      try {
        return (await api.get<{ data: AnneeScolaire }>('/annee-active')).data.data
      } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) return null
        throw error
      }
    },
  })
}

export function useActiverAnnee() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post<{ data: AnneeScolaire }>(`/annees-scolaires/${id}/activer`)).data.data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: annees.keys.all })
      queryClient.invalidateQueries({ queryKey: ['annee-active'] })
    },
  })
}

export function useBasculerCloture() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, cloturer }: { id: number; cloturer: boolean }) =>
      (await api.post<{ data: Periode }>(`/periodes/${id}/${cloturer ? 'cloturer' : 'rouvrir'}`)).data.data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: periodes.keys.all })
      queryClient.invalidateQueries({ queryKey: annees.keys.all })
    },
  })
}

const cleCoefficients = (niveauId: number) => ['coefficients', niveauId] as const

export function useCoefficients(niveauId: number | undefined) {
  return useQuery({
    queryKey: cleCoefficients(niveauId ?? 0),
    queryFn: async () => (await api.get<{ data: LigneCoefficient[] }>(`/niveaux/${niveauId}/coefficients`)).data.data,
    enabled: niveauId !== undefined,
  })
}

export function useEnregistrerCoefficients(niveauId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (lignes: { matiere_id: number; valeur: number | null }[]) =>
      (await api.put<{ data: LigneCoefficient[] }>(`/niveaux/${niveauId}/coefficients`, { coefficients: lignes })).data
        .data,
    onSuccess: (grille) => {
      queryClient.setQueryData(cleCoefficients(niveauId), grille)
      queryClient.invalidateQueries({ queryKey: niveaux.keys.all })
      queryClient.invalidateQueries({ queryKey: matieres.keys.all })
    },
  })
}

/** Liste courte (100 max) pour alimenter les listes déroulantes. */
export const TOUS = { per_page: 100 } as const
