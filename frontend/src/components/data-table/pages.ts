/**
 * Numéros de page à afficher : la première, la dernière et les voisines de la page courante,
 * avec « … » pour les trous. Ex. page 6 sur 12 → [1, '…', 5, 6, 7, '…', 12].
 */
export function pagesAffichees(courante: number, derniere: number, voisins = 1): (number | '…')[] {
  if (derniere <= 1) return [1]

  const pages = new Set([1, derniere])
  for (let p = courante - voisins; p <= courante + voisins; p++) {
    if (p >= 1 && p <= derniere) pages.add(p)
  }

  const triees = [...pages].sort((a, b) => a - b)
  const resultat: (number | '…')[] = []
  triees.forEach((page, i) => {
    const precedente = triees[i - 1]
    if (precedente !== undefined && page - precedente === 2) resultat.push(precedente + 1)
    else if (precedente !== undefined && page - precedente > 2) resultat.push('…')
    resultat.push(page)
  })
  return resultat
}
