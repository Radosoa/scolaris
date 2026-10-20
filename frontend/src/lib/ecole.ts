const CLE = 'scolaris.ecole'

/** Sous-domaines qui ne désignent pas une école. */
const RESERVES = new Set(['www', 'app', 'api'])

/** Domaine dont les sous-domaines désignent les écoles (ex. scolaris.mg), défini au build. */
const DOMAINE_ECOLES = import.meta.env.VITE_DOMAINE_ECOLES

/**
 * Code de l'école porté par l'adresse : « lycee-andohalo.scolaris.mg » quand `domaine` vaut
 * « scolaris.mg », ou en local « lycee-andohalo.localhost ». Une adresse d'hébergeur
 * (« scolaris-erp.vercel.app ») ne désigne pas une école : null.
 */
export function codeDepuisHote(hote: string, domaine?: string): string | null {
  const nom = hote.toLowerCase()
  const parent = nom.endsWith('.localhost') ? 'localhost' : domaine?.toLowerCase()
  if (!parent || !nom.endsWith(`.${parent}`)) return null
  const sousDomaine = nom.slice(0, -parent.length - 1)
  if (!sousDomaine || sousDomaine.includes('.') || RESERVES.has(sousDomaine)) return null
  return sousDomaine
}

/** École désignée par le sous-domaine, sinon la dernière saisie à la connexion. */
export const ecoleStorage = {
  duSousDomaine(): string | null {
    return typeof window === 'undefined' ? null : codeDepuisHote(window.location.hostname, DOMAINE_ECOLES)
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
