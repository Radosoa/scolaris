const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']

/** « 2026-09 » → « sept. 2026 » */
export function libelleMois(mois: string): string {
  const [annee, numero] = mois.split('-').map(Number)
  return `${MOIS[numero - 1]} ${annee}`
}

/** Lundi « 2026-10-12 » → « 12/10 » (axe des semaines). */
export function libelleSemaine(lundi: string): string {
  const [, mois, jour] = lundi.split('-')
  return `${jour}/${mois}`
}

/** Taux d'occupation d'une classe en % (effectif / capacité). */
export function remplissage(effectif: number, capacite: number): number {
  return capacite > 0 ? Math.round((effectif / capacite) * 100) : 0
}

/** Pourcentage français : 16.67 → « 16,7 % ». */
export function formatPourcentage(valeur: number | null | undefined, decimales = 1): string {
  if (valeur === null || valeur === undefined) return '—'
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: decimales }).format(valeur)} %`
}
