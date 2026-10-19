const MOIS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']

export interface TrancheProposee {
  libelle: string
  date_limite: string
  pourcentage: number
}

/**
 * Écolage mensuel : une tranche par mois de l'année scolaire, à régler avant le `jour` du mois
 (pas avant la rentrée). Parts égales au centième ; la dernière absorbe l'arrondi.
 * Ex. 01/09/2026 → 30/06/2027 : 10 tranches de 10 %, « Septembre 2026 » avant le 05/09/2026…
 */
export function echeancierMensuel(dateDebut: string, dateFin: string, jour = 5): TrancheProposee[] {
  const [anneeDebut, moisDebut] = dateDebut.split('-').map(Number)
  const [anneeFin, moisFin] = dateFin.split('-').map(Number)
  const jourFin = Number(dateFin.split('-')[2])
  // Un dernier mois qui se termine avant la date limite (fin d'année le 4 juillet) n'a pas de tranche
  const nombre = (anneeFin - anneeDebut) * 12 + (moisFin - moisDebut) + (jourFin >= jour ? 1 : 0)
  if (nombre < 1) return []

  const part = Math.floor(10000 / nombre) / 100
  const deux = (n: number) => String(n).padStart(2, '0')

  return Array.from({ length: nombre }, (_, i) => {
    const mois = ((moisDebut - 1 + i) % 12) + 1
    const annee = anneeDebut + Math.floor((moisDebut - 1 + i) / 12)
    const date = `${annee}-${deux(mois)}-${deux(jour)}`
    const borne = date < dateDebut ? dateDebut : date
    const pourcentage = i === nombre - 1 ? Math.round((100 - part * (nombre - 1)) * 100) / 100 : part
    return { libelle: `${MOIS[mois - 1]} ${annee}`, date_limite: borne, pourcentage }
  })
}
