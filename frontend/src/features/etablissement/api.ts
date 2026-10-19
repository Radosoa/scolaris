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

export interface IdentiteEcole {
  code: string | null
  nom: string
  adresse: string | null
  ville: string | null
  telephone: string | null
  email: string | null
  chef_etablissement: string | null
}

export const ECOLE_CLE = ['ecole'] as const

/** Identité de l'école courante (menu, en-tête des documents). */
export function useEcole() {
  return useQuery({
    queryKey: ECOLE_CLE,
    queryFn: async () => (await api.get<{ data: IdentiteEcole }>('/ecole')).data.data,
    staleTime: Infinity,
  })
}

export function useModifierEcole() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (valeurs: Omit<IdentiteEcole, 'code'>) => (await api.put<{ data: IdentiteEcole }>('/ecole', valeurs)).data.data,
    onSuccess: (ecole) => queryClient.setQueryData(ECOLE_CLE, ecole),
  })
}
