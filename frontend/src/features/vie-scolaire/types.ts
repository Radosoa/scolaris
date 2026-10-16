export type Jour = 1 | 2 | 3 | 4 | 5 | 6

export interface Creneau {
  id: number
  affectation_id: number
  jour: Jour
  jour_libelle: string
  /** « HH:MM » */
  heure_debut: string
  heure_fin: string
  salle: string | null
  classe?: { id: number; nom: string }
  matiere?: { id: number; nom: string; code: string }
  enseignant?: { id: number; nom_complet: string }
}

export interface EmploiDuTemps {
  data: Creneau[]
  meta: { type: 'classe' | 'enseignant'; id: number; libelle: string; jours: Record<Jour, string> }
}

export interface CreneauPayload {
  affectation_id: number
  jour: number
  heure_debut: string
  heure_fin: string
  salle: string | null
}

export type StatutAppel = 'present' | 'absent' | 'retard'

export interface CoursDuJour {
  creneau: Creneau
  appel_fait: boolean
  appel_fait_le: string | null
}

export interface LigneAppel {
  eleve_id: number
  matricule: string
  nom: string
  prenom: string
  statut: StatutAppel
  minutes_retard: number | null
  justifiee: boolean
}

export interface FeuilleAppel {
  creneau: Creneau
  date: string
  appel: { fait_le: string; fait_par: string | null } | null
  eleves: LigneAppel[]
}

export interface Absence {
  id: number
  type: 'absence' | 'retard'
  type_libelle: string
  date: string
  heure_debut: string
  heure_fin: string
  duree_heures: number | null
  minutes_retard: number | null
  justifiee: boolean
  motif: string | null
  eleve?: { id: number; matricule: string; nom: string; prenom: string }
  classe: { id: number; nom: string } | null
  matiere: string | null
  enseignant: string | null
}

export interface TotauxAbsences {
  heures_justifiees: number
  heures_non_justifiees: number
  retards: number
  a_justifier: number
}
