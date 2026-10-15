export interface Periode {
  id: number
  annee_scolaire_id: number
  libelle: string
  ordre: number
  date_debut: string
  date_fin: string
  cloturee: boolean
}

export interface AnneeScolaire {
  id: number
  libelle: string
  date_debut: string
  date_fin: string
  active: boolean
  periodes_count?: number
  classes_count?: number
  periodes?: Periode[]
}

export interface Niveau {
  id: number
  libelle: string
  ordre: number
  classes_count?: number
  coefficients_count?: number
}

export interface Classe {
  id: number
  nom: string
  capacite: number
  effectif?: number
  niveau_id: number
  annee_scolaire_id: number
  niveau?: Niveau
  annee_scolaire?: { id: number; libelle: string }
}

export interface Matiere {
  id: number
  nom: string
  code: string
  coefficients_count?: number
}

export interface LigneCoefficient {
  matiere_id: number
  nom: string
  code: string
  valeur: number | null
}
