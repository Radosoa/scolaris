import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { telecharger } from '@/lib/download'
import type { ListParams, Paginated, PaginationMeta } from '@/types/api'
import type { Echeance, GrilleFrais, LigneFrais, ModePaiement, Paiement, Situation, Solde, TotauxAnnee } from './types'

const cles = {
  tout: ['finances'] as const,
  situation: (eleveId: number) => ['finances', 'situation', eleveId] as const,
  frais: ['finances', 'frais'] as const,
}

export function useSituation(eleveId: number) {
  return useQuery({
    queryKey: cles.situation(eleveId),
    queryFn: async () => (await api.get<{ data: Situation }>(`/eleves/${eleveId}/finances`)).data.data,
  })
}

export function useImpayes(params: ListParams) {
  return useQuery({
    queryKey: ['finances', 'impayes', params],
    queryFn: async () =>
      (await api.get<{ data: Solde[]; meta: PaginationMeta & { totaux: TotauxAnnee } }>('/impayes', { params })).data,
    placeholderData: keepPreviousData,
  })
}

export function useJournal(params: ListParams) {
  return useQuery({
    queryKey: ['finances', 'journal', params],
    queryFn: async () =>
      (await api.get<Paginated<Paiement> & { meta: PaginationMeta & { total_encaisse: number } }>('/paiements', { params })).data,
    placeholderData: keepPreviousData,
  })
}

export interface PaiementPayload {
  montant: number
  date: string
  mode: ModePaiement
  reference: string | null
}

/** Encaissement : rafraîchit situation, impayés et journal. */
export function useEncaisser(inscriptionId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (donnees: PaiementPayload) =>
      (await api.post<{ data: Paiement }>(`/inscriptions/${inscriptionId}/paiements`, donnees)).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.tout }),
  })
}

export function useAnnulerPaiement() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, motif }: { id: number; motif: string }) =>
      (await api.post<{ data: Paiement }>(`/paiements/${id}/annuler`, { motif })).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.tout }),
  })
}

export const telechargerRecu = (id: number) => telecharger(`/paiements/${id}/recu`, 'recu.pdf')

export function useGrilleFrais() {
  return useQuery({
    queryKey: cles.frais,
    queryFn: async () => (await api.get<GrilleFrais>('/frais')).data,
  })
}

export function useEnregistrerFrais() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...donnees }: Partial<LigneFrais> & { libelle: string; montant: number }) =>
      id ? (await api.put(`/frais/${id}`, donnees)).data : (await api.post('/frais', donnees)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.tout }),
  })
}

export function useSupprimerFrais() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/frais/${id}`)
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.tout }),
  })
}

export function useEnregistrerEcheances(anneeId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (echeances: Echeance[]) =>
      (await api.put<{ data: Echeance[] }>(`/annees-scolaires/${anneeId}/echeances`, { echeances })).data.data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: cles.tout }),
  })
}
