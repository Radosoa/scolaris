import { useState } from 'react'

/**
 * Modale de création / édition / confirmation. L'élément ciblé est conservé pendant
 * l'animation de fermeture pour éviter que le contenu ne change sous les yeux.
 */
export function useDialogState<T>() {
  const [open, setOpen] = useState(false)
  const [item, setItem] = useState<T | null>(null)

  return {
    open,
    item,
    setOpen,
    ouvrir: (cible: T | null = null) => {
      setItem(cible)
      setOpen(true)
    },
  }
}
