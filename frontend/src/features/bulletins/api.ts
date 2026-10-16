import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { telecharger } from '@/lib/download'
import type { Paginated } from '@/types/api'
import type { Appreciations, Bulletin, BulletinsClasse } from './types'

const cles = {
  tout: ['bulletins'] as const,
  classe: (classeId: number, periodeId: number) => ['bulletins', 'classe', classeId, periodeId] as const,
  detail: (id: number) => ['bulletins', 'detail', id] as const,
  eleve: (eleveId: number) => ['bulletins', 'eleve', eleveId] as const,
  appreciations: (affectationId: number, periodeId: number) => ['bulletins', 'appreciations', affectationId, periodeId] as const,
}

const cheminClasse = (classeId: number, periodeId: number) => `/classes/${classeId}/periodes/${periodeId}/bulletins`

/** Bulletins d'une classe (écran de la direction). Rafraîchi toutes les 2 s pendant la génération des PDF. */
export function useBulletinsClasse(classeId: number | undefined, periodeId: number | undefined) {
  return useQuery({
    queryKey: cles.classe(classeId ?? 0, periodeId ?? 0),
    queryFn: async () => (await api.get<BulletinsClasse>(cheminClasse(classeId!, periodeId!))).data,
    enabled: !!classeId && !!periodeId,
    placeholderData: keepPreviousData,
    refetchInterval: (query) => (query.state.data?.meta.pdf_en_cours ? 2000 : false),
  })
}

/** Calcul ou validation des bulletins d'une classe : la réponse contient la liste à jour. */
export function useActionClasse(classeId: number, periodeId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (action: 'calculer' | 'valider') =>
      (await api.post<BulletinsClasse>(`${cheminClasse(classeId, periodeId)}/${action}`)).data,
    onSuccess: (donnees) => {
      queryClient.setQueryData(cles.classe(classeId, periodeId), donnees)
      queryClient.invalidateQueries({ queryKey: cles.tout, refetchType: 'none' })
      // La réouverture de la période devient impossible une fois les bulletins validés
      queryClient.invalidateQueries({ queryKey: ['periodes'] })
    },
  })
}

export function useGenererPdfClasse(classeId: number, periodeId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post<{ message: string }>(`${cheminClasse(classeId, periodeId)}/pdf`)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.classe(classeId, periodeId) }),
  })
}

export const telechargerPdfClasse = (classeId: number, periodeId: number) =>
  telecharger(`${cheminClasse(classeId, periodeId)}/pdf`, 'bulletins.pdf')

export const telechargerPdfBulletin = (id: number) => telecharger(`/bulletins/${id}/pdf`, 'bulletin.pdf')

export function useBulletin(id: number) {
  return useQuery({
    queryKey: cles.detail(id),
    queryFn: async () => (await api.get<{ data: Bulletin }>(`/bulletins/${id}`)).data.data,
  })
}

export function useAppreciationGenerale(id: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (appreciation_generale: string) =>
      (await api.put<{ data: Bulletin }>(`/bulletins/${id}/appreciation`, { appreciation_generale })).data.data,
    onSuccess: (bulletin) => {
      queryClient.setQueryData(cles.detail(id), bulletin)
      queryClient.invalidateQueries({ queryKey: ['bulletins', 'classe'] })
    },
  })
}

/** Bulletins consultables d'un élève (validés, ou tous pour la direction). */
export function useBulletinsEleve(eleveId: number | null | undefined) {
  return useQuery({
    queryKey: cles.eleve(eleveId ?? 0),
    queryFn: async () => (await api.get<Paginated<Bulletin>>(`/eleves/${eleveId}/bulletins`)).data.data,
    enabled: !!eleveId,
  })
}

export function useAppreciations(affectationId: number | undefined, periodeId: number | undefined) {
  return useQuery({
    queryKey: cles.appreciations(affectationId ?? 0, periodeId ?? 0),
    queryFn: async () =>
      (await api.get<{ data: Appreciations }>(`/affectations/${affectationId}/appreciations`, { params: { periode_id: periodeId } }))
        .data.data,
    enabled: !!affectationId && !!periodeId,
    // Pas de rechargement automatique pendant la saisie
    staleTime: Infinity,
    refetchOnReconnect: false,
  })
}

export function useEnregistrerAppreciations(affectationId: number, periodeId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (appreciations: { ligne_id: number; appreciation: string | null }[]) =>
      (
        await api.put<{ data: Appreciations }>(`/affectations/${affectationId}/appreciations`, {
          periode_id: periodeId,
          appreciations,
        })
      ).data.data,
    onSuccess: (donnees) => queryClient.setQueryData(cles.appreciations(affectationId, periodeId), donnees),
  })
}
