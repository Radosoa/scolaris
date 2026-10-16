import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ListParams, Paginated, PaginationMeta } from '@/types/api'
import type { Absence, CoursDuJour, CreneauPayload, EmploiDuTemps, FeuilleAppel, StatutAppel, TotauxAbsences } from './types'

const cles = {
  emploiDuTemps: ['emploi-du-temps'] as const,
  appels: ['appels'] as const,
  absences: ['absences'] as const,
}

/** Emploi du temps d'une classe ou d'un professeur ; sans filtre, le sien. */
export function useEmploiDuTemps(filtre: { classe_id?: number; enseignant_id?: number }, options: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: [...cles.emploiDuTemps, filtre],
    queryFn: async () => (await api.get<EmploiDuTemps>('/emploi-du-temps', { params: filtre })).data,
    placeholderData: keepPreviousData,
    enabled: options.enabled,
  })
}

/** Création ou modification d'un créneau ; les conflits arrivent en 422 (champ « conflits »). */
export function useEnregistrerCreneau() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, donnees }: { id?: number; donnees: CreneauPayload }) =>
      id ? (await api.put(`/creneaux/${id}`, donnees)).data : (await api.post('/creneaux', donnees)).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: cles.emploiDuTemps })
      queryClient.invalidateQueries({ queryKey: cles.appels })
    },
  })
}

export function useSupprimerCreneau() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/creneaux/${id}`)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.emploiDuTemps }),
  })
}

/** Cours du professeur à une date, avec l'état de l'appel. */
export function useCoursDuJour(date: string) {
  return useQuery({
    queryKey: [...cles.appels, 'jour', date],
    queryFn: async () => (await api.get<{ data: CoursDuJour[] }>('/appels', { params: { date } })).data.data,
    placeholderData: keepPreviousData,
  })
}

export function useFeuilleAppel(creneauId: number | undefined, date: string) {
  return useQuery({
    queryKey: [...cles.appels, 'feuille', creneauId, date],
    queryFn: async () => (await api.get<{ data: FeuilleAppel }>(`/creneaux/${creneauId}/appel`, { params: { date } })).data.data,
    enabled: !!creneauId,
    // Pas de rechargement pendant la saisie
    staleTime: Infinity,
    refetchOnReconnect: false,
  })
}

export function useEnregistrerAppel(creneauId: number, date: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (eleves: { eleve_id: number; statut: StatutAppel; minutes_retard: number | null }[]) =>
      (await api.put<{ data: FeuilleAppel }>(`/creneaux/${creneauId}/appel`, { date, eleves })).data.data,
    onSuccess: (feuille) => {
      queryClient.setQueryData([...cles.appels, 'feuille', creneauId, date], feuille)
      queryClient.invalidateQueries({ queryKey: [...cles.appels, 'jour'] })
      queryClient.invalidateQueries({ queryKey: cles.absences })
    },
  })
}

export function useAbsences(params: ListParams) {
  return useQuery({
    queryKey: [...cles.absences, 'liste', params],
    queryFn: async () =>
      (await api.get<Paginated<Absence> & { meta: PaginationMeta & { a_justifier: number } }>('/absences', { params })).data,
    placeholderData: keepPreviousData,
  })
}

export function useJustifier() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...donnees }: { id: number; justifiee: boolean; motif: string | null; toute_la_journee: boolean }) =>
      (await api.put<{ data: Absence; meta: { mises_a_jour: number } }>(`/absences/${id}/justification`, donnees)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.absences }),
  })
}

export function useAbsencesEleve(eleveId: number, page = 1) {
  return useQuery({
    queryKey: [...cles.absences, 'eleve', eleveId, page],
    queryFn: async () =>
      (await api.get<Paginated<Absence> & { totaux: TotauxAbsences }>(`/eleves/${eleveId}/absences`, { params: { page, per_page: 10 } })).data,
    placeholderData: keepPreviousData,
  })
}
