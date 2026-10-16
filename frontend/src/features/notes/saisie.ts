import type { LigneGrille, Statistiques } from './types'
import type { LigneSaisie } from './api'

/** État d'une ligne de la grille pendant la saisie : le texte tapé est conservé tel quel. */
export interface LigneEdition {
  eleve_id: number
  texte: string
  absent: boolean
}

export type ResultatLecture = { ok: true; valeur: number | null } | { ok: false; erreur: string }

/**
 * Lit une note saisie au clavier : accepte la virgule ou le point, deux décimales au plus,
 * entre 0 et le barème. Une saisie vide signifie « pas de note ».
 */
export function lireNote(texte: string, bareme: number): ResultatLecture {
  const nettoye = texte.trim().replace(',', '.')
  if (nettoye === '') return { ok: true, valeur: null }
  if (!/^\d+(\.\d{1,2})?$/.test(nettoye)) {
    return { ok: false, erreur: /^-/.test(nettoye) ? 'Note négative.' : 'Nombre invalide (2 décimales max.).' }
  }
  const valeur = Number(nettoye)
  if (valeur > bareme) return { ok: false, erreur: `Maximum ${formatNote(bareme)}.` }
  return { ok: true, valeur }
}

export function formatNote(valeur: number): string {
  return valeur.toLocaleString('fr-FR', { maximumFractionDigits: 2 })
}

export function lignesInitiales(eleves: LigneGrille[]): LigneEdition[] {
  return eleves.map((e) => ({
    eleve_id: e.eleve_id,
    texte: e.valeur === null ? '' : formatNote(e.valeur),
    absent: e.absent,
  }))
}

/** Lignes dont le contenu diffère de l'état enregistré (comparaison sur la valeur lue, pas le texte). */
export function lignesModifiees(lignes: LigneEdition[], eleves: LigneGrille[], bareme: number): LigneEdition[] {
  const origine = new Map(eleves.map((e) => [e.eleve_id, e]))
  return lignes.filter((ligne) => {
    const avant = origine.get(ligne.eleve_id)
    if (!avant) return true
    if (ligne.absent !== avant.absent) return true
    if (ligne.absent) return false
    const lecture = lireNote(ligne.texte, bareme)
    return !lecture.ok || lecture.valeur !== avant.valeur
  })
}

/** Lignes à envoyer à l'API ; suppose que toutes les lignes sont valides. */
export function versPayload(lignes: LigneEdition[], bareme: number): LigneSaisie[] {
  return lignes.map((ligne) => {
    const lecture = lireNote(ligne.texte, bareme)
    return {
      eleve_id: ligne.eleve_id,
      absent: ligne.absent,
      valeur: ligne.absent || !lecture.ok ? null : lecture.valeur,
    }
  })
}

/** Statistiques calculées en direct sur la saisie en cours (absents et notes invalides exclus). */
export function statistiquesSaisie(lignes: LigneEdition[], bareme: number): Statistiques {
  const valeurs: number[] = []
  let absents = 0
  for (const ligne of lignes) {
    if (ligne.absent) {
      absents++
      continue
    }
    const lecture = lireNote(ligne.texte, bareme)
    if (lecture.ok && lecture.valeur !== null) valeurs.push(lecture.valeur)
  }
  const moyenne = valeurs.length ? Math.round((valeurs.reduce((s, v) => s + v, 0) / valeurs.length) * 100) / 100 : null
  return {
    saisies: valeurs.length + absents,
    absents,
    moyenne,
    min: valeurs.length ? Math.min(...valeurs) : null,
    max: valeurs.length ? Math.max(...valeurs) : null,
  }
}
