import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ListParams, Paginated } from '@/types/api'

/** Retire les paramètres vides pour garder des URL et des clés de cache propres. */
function nettoyer(params: ListParams): ListParams {
  return Object.fromEntries(
    Object.entries(params).filter(([, valeur]) => valeur !== undefined && valeur !== ''),
  ) as ListParams
}

/**
 * Fabrique les appels API et les hooks TanStack Query d'une ressource REST Laravel
 * (index paginé, show, store, update, destroy).
 */
export function createResource<T extends { id: number }, Payload = Partial<T>>(cle: string, chemin: string) {
  const keys = {
    all: [cle] as const,
    list: (params: ListParams) => [cle, 'list', nettoyer(params)] as const,
    detail: (id: number) => [cle, 'detail', id] as const,
  }

  const endpoints = {
    list: async (params: ListParams = {}) =>
      (await api.get<Paginated<T>>(chemin, { params: nettoyer(params) })).data,
    get: async (id: number) => (await api.get<{ data: T }>(`${chemin}/${id}`)).data.data,
    create: async (donnees: Payload) => (await api.post<{ data: T }>(chemin, donnees)).data.data,
    update: async (id: number, donnees: Payload) =>
      (await api.put<{ data: T }>(`${chemin}/${id}`, donnees)).data.data,
    remove: async (id: number) => {
      await api.delete(`${chemin}/${id}`)
    },
  }

  function useList(params: ListParams = {}, options: { enabled?: boolean } = {}) {
    return useQuery({
      queryKey: keys.list(params),
      queryFn: () => endpoints.list(params),
      placeholderData: keepPreviousData,
      enabled: options.enabled,
    })
  }

  function useDetail(id: number | undefined) {
    return useQuery({
      queryKey: keys.detail(id ?? 0),
      queryFn: () => endpoints.get(id!),
      enabled: id !== undefined,
    })
  }

  /** Création si id est absent, mise à jour sinon. Invalide tout le cache de la ressource. */
  function useSave() {
    const queryClient = useQueryClient()
    return useMutation({
      mutationFn: ({ id, donnees }: { id?: number; donnees: Payload }) =>
        id ? endpoints.update(id, donnees) : endpoints.create(donnees),
      onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.all }),
    })
  }

  function useRemove() {
    const queryClient = useQueryClient()
    return useMutation({
      mutationFn: endpoints.remove,
      onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.all }),
    })
  }

  return { keys, endpoints, useList, useDetail, useSave, useRemove }
}
