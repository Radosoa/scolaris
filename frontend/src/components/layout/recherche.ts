import type { NavGroup, NavItem } from '@/config/navigation'

/** Minuscules sans accents : « Élèves » et « eleves » se valent. */
export function normaliser(texte: string): string {
  return texte.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
}

export interface PageTrouvee extends NavItem {
  groupe: string | null
}

/**
 * Écrans accessibles dont le libellé ou le groupe contient chacun des mots saisis.
 * Sans saisie, tous les écrans sont proposés dans l'ordre du menu.
 */
export function filtrerPages(groupes: NavGroup[], saisie: string): PageTrouvee[] {
  const mots = normaliser(saisie).split(/\s+/).filter(Boolean)
  return groupes
    .flatMap((g) => g.items.map((item) => ({ ...item, groupe: g.label })))
    .filter((page) => {
      const cible = normaliser(`${page.label} ${page.groupe ?? ''}`)
      return mots.every((mot) => cible.includes(mot))
    })
}

/** Index suivant / précédent dans une liste, en boucle. */
export function deplacer(index: number, delta: 1 | -1, longueur: number): number {
  if (longueur === 0) return 0
  return (index + delta + longueur) % longueur
}
