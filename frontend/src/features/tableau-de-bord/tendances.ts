export type Sens = 'hausse' | 'baisse' | 'stable'

export interface Variation {
  /** Écart relatif en %, arrondi à l'unité ; null si la référence est nulle */
  pourcentage: number | null
  sens: Sens
}

/** Variation d'une valeur par rapport à la période précédente (« stable » sous 1 %). */
export function variation(actuelle: number, precedente: number): Variation {
  if (precedente === 0) {
    return { pourcentage: null, sens: actuelle > 0 ? 'hausse' : 'stable' }
  }
  const pourcentage = Math.round(((actuelle - precedente) / precedente) * 100)
  return { pourcentage, sens: Math.abs(pourcentage) < 1 ? 'stable' : pourcentage > 0 ? 'hausse' : 'baisse' }
}

/**
 * Une hausse est-elle une bonne nouvelle ? Ça dépend de l'indicateur :
 * plus d'encaissements oui, plus d'absences non.
 */
export function tonVariation(sens: Sens, hausseFavorable: boolean): 'positif' | 'negatif' | 'neutre' {
  if (sens === 'stable') return 'neutre'
  return (sens === 'hausse') === hausseFavorable ? 'positif' : 'negatif'
}

/** « +12 % », « −8 % », « = » ; « nouveau » quand il n'y avait rien avant. */
export function libelleVariation(v: Variation): string {
  if (v.sens === 'stable') return '='
  if (v.pourcentage === null) return 'nouveau'
  return `${v.pourcentage > 0 ? '+' : '−'}${Math.abs(v.pourcentage)} %`
}
