const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })

/** 1250.5 → « 1 250,50 € » */
export function formatEuros(montant: number | null | undefined): string {
  return montant === null || montant === undefined ? '—' : euros.format(montant)
}

/**
 * Saisie d'un montant en français : « 1 250,50 », « 1250.5 », « 300 € » → 1250.5 / 300.
 * Renvoie null si la saisie n'est pas un montant positif avec au plus deux décimales.
 */
export function lireMontant(saisie: string): number | null {
  const nettoye = saisie.replace(/[\s  €]/g, '').replace(',', '.')
  if (!/^\d+(\.\d{1,2})?$/.test(nettoye)) return null
  const valeur = Number(nettoye)
  return valeur > 0 ? valeur : null
}

/** Part réglée, de 0 à 100 (barre de progression). */
export function progression(paye: number, total: number): number {
  if (total <= 0) return 0
  return Math.min(100, Math.round((paye / total) * 100))
}

/** Somme des pourcentages d'un échéancier, au centième près (évite 33,33 + 66,67 = 100,00000001). */
export function totalPourcentages(lignes: { pourcentage: number }[]): number {
  return Math.round(lignes.reduce((total, l) => total + (Number.isFinite(l.pourcentage) ? l.pourcentage : 0), 0) * 100) / 100
}
