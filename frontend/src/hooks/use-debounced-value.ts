import { useEffect, useState } from 'react'

/** Valeur mise à jour seulement après `delai` ms sans changement (recherche à la frappe). */
export function useDebouncedValue<T>(valeur: T, delai = 300): T {
  const [differee, setDifferee] = useState(valeur)

  useEffect(() => {
    const minuteur = setTimeout(() => setDifferee(valeur), delai)
    return () => clearTimeout(minuteur)
  }, [valeur, delai])

  return differee
}
