import type { Affectation } from '@/features/personnes/types'

export type TypeEvaluation = 'devoir' | 'interrogation' | 'composition'

export const TYPES_EVALUATION: Record<TypeEvaluation, string> = {
  devoir: 'Devoir',
  interrogation: 'Interrogation',
  composition: 'Composition',
}

export interface Evaluation {
  id: number
  affectation_id: number
  periode_id: number
  type: TypeEvaluation
  type_libelle: string
  titre: string
  date: string
  bareme: number
  verrouillee?: boolean
  periode?: { id: number; libelle: string }
  affectation?: Affectation
  notes_count?: number
  moyenne?: number | null
}

export interface LigneGrille {
  eleve_id: number
  matricule: string
  nom: string
  prenom: string
  /** false si l'élève a quitté la classe (transfert, abandon) mais a déjà une note */
  inscrit: boolean
  valeur: number | null
  absent: boolean
}

export interface Statistiques {
  saisies: number
  absents: number
  moyenne: number | null
  min: number | null
  max: number | null
}

export interface Grille {
  evaluation: Evaluation
  eleves: LigneGrille[]
  statistiques: Statistiques
}

export interface NoteReleve {
  id: number
  titre: string
  type: TypeEvaluation
  type_libelle: string
  date: string
  bareme: number
  valeur: number | null
  absent: boolean
}

export interface Releve {
  periodes: { id: number; libelle: string; cloturee: boolean }[]
  periode_id: number | null
  matieres: {
    matiere: { id: number; nom: string; code: string }
    enseignant: string
    evaluations: NoteReleve[]
  }[]
}
