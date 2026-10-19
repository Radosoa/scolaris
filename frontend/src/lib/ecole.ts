const CLE = 'scolaris.ecole'

/** Sous-domaines qui ne désignent pas une école. */
const RESERVES = new Set(['www', 'app', 'api'])

/**
 * Code de l'école porté par l'adresse : « lycee-andohalo.scolaris.mg » ou, en local,
 * « lycee-andohalo.localhost ». Null pour « localhost », « scolaris.mg » ou une adresse IP.
 */
export function codeDepuisHote(hote: string): string | null {
  if (/^[\d.]+$/.test(hote) || hote.includes(':')) return null
  const parties = hote.toLowerCase().split('.')
  const local = parties.at(-1) === 'localhost'
  if (parties.length < (local ? 2 : 3)) return null
  return RESERVES.has(parties[0]) ? null : parties[0]
}

/** École désignée par le sous-domaine, sinon la dernière saisie à la connexion. */
export const ecoleStorage = {
  duSousDomaine(): string | null {
    return typeof window === 'undefined' ? null : codeDepuisHote(window.location.hostname)
  },
  get(): string | null {
    const sousDomaine = this.duSousDomaine()
    if (sousDomaine) return sousDomaine
    try {
      return localStorage.getItem(CLE)
    } catch {
      return null
    }
  },
  set(code: string): void {
    try {
      localStorage.setItem(CLE, code)
    } catch {
      /* gardé pour la session en cours seulement */
    }
  },
}
