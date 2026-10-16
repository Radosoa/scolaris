import type { Bulletin } from './types'

const deuxDecimales = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

/** Moyenne sur 20, toujours avec 2 décimales : « 13,60 ». */
export function formatMoyenne(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '—' : deuxDecimales.format(valeur)
}

/** « 1er », « 2e », « 2e ex æquo », « Non classé ». */
export function formatRang(rang: number | null | undefined, exAequo = false): string {
  if (rang === null || rang === undefined) return 'Non classé'
  return `${rang === 1 ? '1er' : `${rang}e`}${exAequo ? ' ex æquo' : ''}`
}

/** Heures d'absence : « 2 h », « 1,5 h ». */
export function formatHeures(heures: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(heures)} h`
}

/** Rangs partagés par plusieurs élèves d'une même liste de classe. */
export function rangsExAequo(bulletins: Pick<Bulletin, 'rang'>[]): Set<number> {
  const occurrences = new Map<number, number>()
  for (const { rang } of bulletins) {
    if (rang !== null) occurrences.set(rang, (occurrences.get(rang) ?? 0) + 1)
  }
  return new Set([...occurrences].filter(([, n]) => n > 1).map(([rang]) => rang))
}

/** Part des élèves classés ayant au moins 10/20 (null si personne n'est classé). */
export function tauxReussite(bulletins: Pick<Bulletin, 'moyenne'>[]): number | null {
  const classes = bulletins.filter((b) => b.moyenne !== null)
  if (classes.length === 0) return null
  return classes.filter((b) => b.moyenne! >= 10).length / classes.length
}

export type TonMention = 'excellent' | 'bon' | 'moyen' | 'faible'

/** Ton visuel d'une moyenne, aligné sur les seuils de mention par défaut. */
export function tonMoyenne(moyenne: number | null | undefined): TonMention | null {
  if (moyenne === null || moyenne === undefined) return null
  if (moyenne >= 14) return 'excellent'
  if (moyenne >= 12) return 'bon'
  if (moyenne >= 10) return 'moyen'
  return 'faible'
}
