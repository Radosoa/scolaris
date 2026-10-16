export type EtatBulletins = 'non_calcule' | 'brouillon' | 'valide'

export interface LigneBulletin {
  id: number
  matiere: { id: number; nom: string; code: string }
  enseignant: string | null
  coefficient: number
  /** null : matière sans note, exclue de la moyenne générale */
  moyenne: number | null
  rang: number | null
  moyenne_classe: number | null
  moyenne_max: number | null
  moyenne_min: number | null
  appreciation: string | null
}

export interface Bulletin {
  id: number
  periode_id: number
  periode?: { id: number; libelle: string; annee: string | null }
  eleve?: { id: number; matricule: string; nom: string; prenom: string }
  classe?: { id: number; nom: string }
  /** Moyennes arrondies à 2 décimales par l'API (les rangs utilisent les valeurs exactes) */
  moyenne: number | null
  rang: number | null
  ex_aequo?: boolean
  effectif: number
  mention: string | null
  appreciation_generale: string | null
  moyenne_classe: number | null
  moyenne_max: number | null
  moyenne_min: number | null
  absences: { heures_justifiees: number; heures_non_justifiees: number; retards: number }
  valide: boolean
  valide_le: string | null
  calcule_le: string | null
  lignes?: LigneBulletin[]
}

export interface EtatClasse {
  classe: { id: number; nom: string }
  periode: { id: number; libelle: string; cloturee: boolean }
  etat: EtatBulletins
  calcule_le: string | null
  valide_le: string | null
  nombre: number
  pdf_generes: number
  pdf_en_cours: boolean
  pdf_classe: boolean
}

export interface BulletinsClasse {
  data: Bulletin[]
  meta: EtatClasse
}

export interface LigneAppreciation {
  id: number
  eleve: { id: number; nom: string; prenom: string }
  moyenne: number | null
  rang: number | null
  appreciation: string | null
}

export interface Appreciations {
  classe: { id: number; nom: string }
  matiere: { id: number; nom: string }
  periode: { id: number; libelle: string; cloturee: boolean }
  /** false tant que la direction n'a pas calculé les bulletins */
  calcule: boolean
  /** true une fois les bulletins validés */
  verrouille: boolean
  lignes: LigneAppreciation[]
}
