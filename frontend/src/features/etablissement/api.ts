import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { definirDevise, type Devise } from '@/features/finances/format'
import { api } from '@/lib/api'

export interface Parametres {
  devise: Devise
  /** Devises proposées à la direction */
  devises: Devise[]
}

export const PARAMETRES_CLE = ['parametres'] as const

/** La devise est appliquée dès réception : tous les montants affichés la suivent. */
function appliquer(parametres: Parametres): Parametres {
  definirDevise(parametres.devise)
  return parametres
}

export function useParametres() {
  return useQuery({
    queryKey: PARAMETRES_CLE,
    queryFn: async () => appliquer((await api.get<{ data: Parametres }>('/parametres')).data.data),
    staleTime: Infinity,
  })
}

export function useModifierParametres() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (valeurs: { devise: string }) => (await api.put<{ data: Parametres }>('/parametres', valeurs)).data.data,
    onSuccess: (parametres) => queryClient.setQueryData(PARAMETRES_CLE, appliquer(parametres)),
  })
}
