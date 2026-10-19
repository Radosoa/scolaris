import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface NotificationApp {
  id: string
  type: 'retard_ecolage' | 'recap_retards' | null
  titre: string
  message: string
  lien: string | null
  lu: boolean
  cree_le: string
}

const CLE = ['notifications'] as const

/** Rafraîchies chaque minute : les avertissements arrivent sans recharger la page. */
export function useNotifications() {
  return useQuery({
    queryKey: CLE,
    queryFn: async () => (await api.get<{ data: NotificationApp[]; meta: { non_lues: number } }>('/notifications')).data,
    refetchInterval: 60_000,
  })
}

export function useLireNotification() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => api.post(`/notifications/${id}/lire`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLE }),
  })
}

export function useToutLire() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async () => api.post('/notifications/tout-lire'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CLE }),
  })
}
