import type { Creneau, Jour, LigneAppel, StatutAppel } from './types'

/** « 08:30 » → 8,5 */
export function heureDecimale(heure: string): number {
  const [h, m] = heure.split(':').map(Number)
  return h + m / 60
}

/** 8,5 → « 08:30 » */
export function formatHeure(decimale: number): string {
  const minutes = Math.round(decimale * 60)
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
}

export interface Plage {
  /** Heure de la première ligne de la grille (entière) */
  debut: number
  /** Heure de la dernière limite de la grille (entière) */
  fin: number
  jours: Jour[]
}

/**
 * Plage affichée : au moins 8 h – 17 h du lundi au vendredi, élargie pour contenir
 * tous les créneaux (samedi inclus s'il y a cours).
 */
export function plageDe(creneaux: Pick<Creneau, 'jour' | 'heure_debut' | 'heure_fin'>[]): Plage {
  const debut = Math.min(8, ...creneaux.map((c) => Math.floor(heureDecimale(c.heure_debut))))
  const fin = Math.max(17, ...creneaux.map((c) => Math.ceil(heureDecimale(c.heure_fin))))
  const samedi = creneaux.some((c) => c.jour === 6)
  return { debut, fin, jours: samedi ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5] }
}

/** Position verticale d'un créneau dans la grille, en pourcentage de la hauteur. */
export function position(creneau: Pick<Creneau, 'heure_debut' | 'heure_fin'>, plage: Plage): { top: number; height: number } {
  const total = plage.fin - plage.debut
  const debut = heureDecimale(creneau.heure_debut)
  const fin = heureDecimale(creneau.heure_fin)
  return { top: ((debut - plage.debut) / total) * 100, height: ((fin - debut) / total) * 100 }
}

/** Heures de cours par semaine. */
export function totalHeures(creneaux: Pick<Creneau, 'heure_debut' | 'heure_fin'>[]): number {
  return creneaux.reduce((total, c) => total + heureDecimale(c.heure_fin) - heureDecimale(c.heure_debut), 0)
}

/** Teinte stable par matière (index dans la palette), dérivée du code. */
export function teinteMatiere(code: string, nombreDeTeintes: number): number {
  let somme = 0
  for (const caractere of code) somme = (somme * 31 + caractere.charCodeAt(0)) % 1_000_003
  return somme % nombreDeTeintes
}

/** Date ISO (AAAA-MM-JJ) → jour de la semaine 1 (lundi) … 7 (dimanche). */
export function jourIso(date: string): number {
  const [a, m, j] = date.split('-').map(Number)
  const jour = new Date(Date.UTC(a, m - 1, j)).getUTCDay()
  return jour === 0 ? 7 : jour
}

/** Dernière date (aujourd'hui inclus) tombant ce jour de la semaine : la séance dont on fait l'appel. */
export function derniereOccurrence(jour: number, aujourdhui: string): string {
  const [a, m, j] = aujourdhui.split('-').map(Number)
  const date = new Date(Date.UTC(a, m - 1, j))
  date.setUTCDate(date.getUTCDate() - ((jourIso(aujourdhui) - jour + 7) % 7))
  return date.toISOString().slice(0, 10)
}

/** Date locale du jour au format AAAA-MM-JJ (pas toISOString, qui passe en UTC). */
export function aujourdhuiIso(maintenant: Date = new Date()): string {
  const deux = (n: number) => String(n).padStart(2, '0')
  return `${maintenant.getFullYear()}-${deux(maintenant.getMonth() + 1)}-${deux(maintenant.getDate())}`
}

/** Décompte d'une feuille d'appel. */
export function compterAppel(lignes: Pick<LigneAppel, 'statut'>[]): Record<StatutAppel, number> {
  return lignes.reduce(
    (total, l) => ({ ...total, [l.statut]: total[l.statut] + 1 }),
    { present: 0, absent: 0, retard: 0 } as Record<StatutAppel, number>,
  )
}
