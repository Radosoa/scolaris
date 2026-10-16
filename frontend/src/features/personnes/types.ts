export type Sexe = 'M' | 'F'
export type LienParente = 'pere' | 'mere' | 'tuteur' | 'autre'
export type StatutInscription = 'inscrit' | 'transfere' | 'abandon'

export const LIENS: Record<LienParente, string> = {
  pere: 'Père',
  mere: 'Mère',
  tuteur: 'Tuteur légal',
  autre: 'Autre',
}

export const STATUTS: Record<StatutInscription, string> = {
  inscrit: 'Inscrit',
  transfere: 'Transféré',
  abandon: 'Abandon',
}

export interface Compte {
  email: string
  actif: boolean
}

export interface Inscription {
  id: number
  statut: StatutInscription
  statut_libelle: string
  date_inscription: string
  eleve_id: number
  classe_id: number
  annee_scolaire_id: number
  eleve?: { id: number; matricule: string; nom_complet: string; sexe: Sexe }
  classe?: { id: number; nom: string }
  annee_scolaire?: { id: number; libelle: string; active: boolean }
}

export interface TuteurLie {
  id: number
  nom_complet: string
  telephone: string
  email: string | null
  lien: LienParente
  lien_libelle: string
}

export interface Eleve {
  id: number
  matricule: string
  nom: string
  prenom: string
  nom_complet: string
  sexe: Sexe
  date_naissance: string
  lieu_naissance: string | null
  photo_url: string | null
  compte?: Compte | null
  tuteurs?: TuteurLie[]
  inscription_active?: Inscription | null
  inscriptions?: Inscription[]
  archive: boolean
}

export interface Tuteur {
  id: number
  nom: string
  prenom: string
  nom_complet: string
  telephone: string
  email: string | null
  eleves_count?: number
  compte?: Compte | null
  eleves?: { id: number; matricule: string; nom_complet: string; lien: LienParente; lien_libelle: string }[]
}

export interface Affectation {
  id: number
  enseignant_id: number
  matiere_id: number
  classe_id: number
  enseignant?: { id: number; nom_complet: string }
  matiere?: { id: number; nom: string; code: string }
  classe?: { id: number; nom: string; annee_scolaire_id: number }
}

export interface Enseignant {
  id: number
  nom: string
  prenom: string
  nom_complet: string
  specialite: string | null
  telephone: string | null
  email?: string
  actif?: boolean
  affectations_count?: number
  affectations?: Affectation[]
}

export interface LigneImport {
  ligne: number
  donnees: {
    nom: string
    prenom: string
    sexe: Sexe | null
    date_naissance: string | null
    lieu_naissance: string | null
    classe: string | null
  }
  erreurs: string[]
}

export interface AnalyseImport {
  total: number
  valides: number
  lignes: LigneImport[]
}

export interface ResultatImport {
  total: number
  importes: number
  erreurs: { ligne: number; erreurs: string[] }[]
}
