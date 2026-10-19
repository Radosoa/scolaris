/** Devise de l'établissement, choisie par la direction (écran Paramètres). */
export interface Devise {
  code: string
  libelle: string
  symbole: string
  decimales: number
}

export const DEVISE_PAR_DEFAUT: Devise = { code: 'EUR', libelle: 'Euro', symbole: '€', decimales: 2 }

let devise = DEVISE_PAR_DEFAUT

/**
 * Appelée au chargement des paramètres (et après leur modification). Le contenu de
 * l'application est remonté à chaque changement de devise, les montants suivent donc.
 */
export function definirDevise(nouvelle: Devise): void {
  devise = nouvelle
}

export function deviseActive(): Devise {
  return devise
}

/** Espace insécable : le symbole ne passe jamais seul à la ligne. */
const INSECABLE = String.fromCharCode(0xa0)

const formateurs = new Map<string, Intl.NumberFormat>()

function nombre(decimales: number, compact = false): Intl.NumberFormat {
  const cle = `${decimales}-${compact}`
  let formateur = formateurs.get(cle)
  if (!formateur) {
    formateur = compact
      ? new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 })
      : new Intl.NumberFormat('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales })
    formateurs.set(cle, formateur)
  }
  return formateur
}

/** 1250.5 → « 1 250,50 € » ; en ariary (sans décimales) → « 1 251 Ar ». Espace insécable avant le symbole. */
export function formatMontant(montant: number | null | undefined, d: Devise = devise): string {
  return montant === null || montant === undefined ? '—' : `${nombre(d.decimales).format(montant)}${INSECABLE}${d.symbole}`
}

/** Axes et étiquettes de graphiques : 117 000 → « 117 k € », 1 250 000 → « 1,3 M Ar ». */
export function formatMontantCompact(montant: unknown, d: Devise = devise): string {
  return `${nombre(0, true).format(Number(montant))}${INSECABLE}${d.symbole}`
}

/** Valeur numérique → texte d'un champ de saisie : 1250.5 → « 1250,50 » (« 1251 » en ariary). */
export function montantEnSaisie(montant: number, d: Devise = devise): string {
  return montant.toFixed(d.decimales).replace('.', ',')
}

/**
 * Saisie d'un montant en français : « 1 250,50 », « 1250.5 », « 300 € » → 1250.5 / 300.
 * Renvoie null si la saisie n'est pas un montant positif, ou a plus de décimales que la devise.
 */
export function lireMontant(saisie: string, d: Devise = devise): number | null {
  const nettoye = saisie.replaceAll(d.symbole, '').replace(/\s/g, '').replace(',', '.')
  const motif = d.decimales > 0 ? new RegExp(`^\\d+(\\.\\d{1,${d.decimales}})?$`) : /^\d+$/
  if (!motif.test(nettoye)) return null
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
