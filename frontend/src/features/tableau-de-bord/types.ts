import type { Annonce } from '@/features/annonces/types'
import type { TotauxAnnee } from '@/features/finances/types'
import type { TotauxAbsences } from '@/features/vie-scolaire/types'

export interface TableauDirection {
  type: 'direction'
  annee: string
  compteurs: { eleves: number; classes: number; enseignants: number }
  effectifs: { classe: { id: number; nom: string }; capacite: number; effectif: number; garcons: number; filles: number }[]
  resultats: {
    periode: { id: number; libelle: string } | null
    moyenne: number | null
    taux_reussite: number | null
    classes: { classe: { id: number; nom: string }; moyenne: number; max: number; min: number; taux_reussite: number; valides: boolean }[]
  }
  absenteisme: {
    heures_justifiees: number
    heures_non_justifiees: number
    retards: number
    a_justifier: number
    taux: number | null
    par_semaine: { semaine: string; justifiees: number; non_justifiees: number }[]
  }
  finances: TotauxAnnee & { par_mois: { mois: string; montant: number }[] }
}

export interface TableauSecretariat {
  type: 'secretariat'
  annee: string
  eleves: number
  absences: { a_justifier: number; aujourdhui: number }
  finances: TotauxAnnee
  encaissements_7_jours: { montant: number; nombre: number }
  derniers_paiements: { id: number; numero_recu: string; date: string; montant: number; annule: boolean; eleve: { id: number; nom: string }; classe: string }[]
  annonces: Annonce[]
}

export interface TableauProfesseur {
  type: 'professeur'
  cours_du_jour: { creneau_id: number; heure_debut: string; heure_fin: string; salle: string | null; classe: string; matiere: string; appel_fait: boolean }[]
  appels_en_attente: { creneau_id: number; date: string; heure_debut: string; classe: string; matiere: string }[]
  classes: { affectation_id: number; classe: string; matiere: string; effectif: number; moyenne: number | null; periode: string | null }[]
  annonces: Annonce[]
}

export interface SuiviEleve {
  id: number
  nom_complet: string
  classe: string | null
  dernier_bulletin: { id: number; periode: string; moyenne: number | null; rang: number | null; effectif: number; mention: string | null } | null
  dernieres_notes: { id: number; matiere: string; titre: string; date: string; valeur: number | null; bareme: number; absent: boolean }[]
  absences: TotauxAbsences
  cours_du_jour: { heure_debut: string; heure_fin: string; matiere: string; enseignant: string; salle: string | null }[] | null
  finances: { total: number; paye: number; reste: number; retard: number } | null
}

export interface TableauEleve {
  type: 'eleve'
  eleve: SuiviEleve | null
  annonces: Annonce[]
}

export interface TableauParent {
  type: 'parent'
  enfants: SuiviEleve[]
  annonces: Annonce[]
}

export type TableauDeBord = TableauDirection | TableauSecretariat | TableauProfesseur | TableauEleve | TableauParent
