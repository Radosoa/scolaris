import { useState } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'

/**
 * État d'une liste paginée avec recherche : la page revient à 1 quand la recherche change,
 * et la requête n'est envoyée qu'après une courte pause de frappe.
 */
export function useListState(perPage = 15) {
  const [page, setPage] = useState(1)
  const [recherche, setRechercheBrute] = useState('')
  const q = useDebouncedValue(recherche.trim())

  const setRecherche = (valeur: string) => {
    setRechercheBrute(valeur)
    setPage(1)
  }

  return { page, setPage, recherche, setRecherche, params: { page, per_page: perPage, q } }
}
