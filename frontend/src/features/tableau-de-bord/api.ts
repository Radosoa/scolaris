import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { TableauDeBord } from './types'

export function useTableauDeBord() {
  return useQuery({
    queryKey: ['tableau-de-bord'],
    queryFn: async () => (await api.get<{ data: TableauDeBord }>('/tableau-de-bord')).data.data,
    // Indicateurs recalculés côté serveur : un rafraîchissement par minute suffit
    staleTime: 60_000,
  })
}
