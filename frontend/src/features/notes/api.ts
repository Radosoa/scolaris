import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { createResource } from '@/lib/resource'
import type { Evaluation, Grille, Releve, TypeEvaluation } from './types'

export interface EvaluationPayload {
  affectation_id?: number
  periode_id: number
  type: TypeEvaluation
  titre: string
  date: string
  bareme: number
}

export const evaluations = createResource<Evaluation, EvaluationPayload>('evaluations', '/evaluations')

const cleGrille = (id: number) => ['evaluations', 'grille', id] as const

export function useGrille(evaluationId: number) {
  return useQuery({
    queryKey: cleGrille(evaluationId),
    queryFn: async () => (await api.get<{ data: Grille }>(`/evaluations/${evaluationId}/notes`)).data.data,
    // Pas de rechargement automatique pendant la saisie (il réinitialiserait la grille)
    staleTime: Infinity,
    refetchOnReconnect: false,
  })
}

export interface LigneSaisie {
  eleve_id: number
  valeur: number | null
  absent: boolean
}

export function useEnregistrerNotes(evaluationId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (notes: LigneSaisie[]) =>
      (await api.put<{ data: Grille }>(`/evaluations/${evaluationId}/notes`, { notes })).data.data,
    onSuccess: (grille) => {
      queryClient.setQueryData(cleGrille(evaluationId), grille)
      // Statistiques de la liste des évaluations (nombre de notes, moyenne)
      queryClient.invalidateQueries({ queryKey: evaluations.keys.all, refetchType: 'none' })
    },
  })
}

export function useReleve(eleveId: number | null | undefined, periodeId?: number) {
  return useQuery({
    queryKey: ['releve', eleveId, periodeId ?? 'courante'],
    queryFn: async () =>
      (await api.get<{ data: Releve }>(`/eleves/${eleveId}/notes`, { params: periodeId ? { periode_id: periodeId } : {} })).data
        .data,
    enabled: !!eleveId,
    placeholderData: keepPreviousData,
  })
}
