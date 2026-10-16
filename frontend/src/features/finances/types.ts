export type ModePaiement = 'especes' | 'cheque' | 'virement' | 'carte'

export const MODES: Record<ModePaiement, string> = {
  virement: 'Virement',
  cheque: 'Chèque',
  carte: 'Carte bancaire',
  especes: 'Espèces',
}

export interface Paiement {
  id: number
  numero_recu: string
  montant: number
  date: string
  mode: ModePaiement
  mode_libelle: string
  reference: string | null
  encaisse_par?: string | null
  annule: boolean
  annule_le: string | null
  motif_annulation: string | null
  inscription_id: number
  eleve?: { id: number; matricule: string; nom: string; prenom: string }
  classe?: { id: number; nom: string }
}

export type StatutEcheance = 'reglee' | 'en_retard' | 'a_venir'

export interface Tranche {
  libelle: string
  date_limite: string
  pourcentage: number
  montant: number
  cumul: number
  statut: StatutEcheance
}

export interface Situation {
  inscription_id: number
  eleve: { id: number; matricule: string; nom: string; prenom: string }
  classe: { id: number; nom: string }
  annee: string
  frais: { id: number; libelle: string; montant: number }[]
  total: number
  paye: number
  reste: number
  exigible: number
  retard: number
  echeances: Tranche[]
  paiements: Paiement[]
}

export interface Solde {
  inscription_id: number
  eleve: { id: number; matricule: string; nom: string; prenom: string }
  classe: { id: number; nom: string }
  total: number
  paye: number
  reste: number
  retard: number
}

export interface TotauxAnnee {
  attendu: number
  encaisse: number
  reste: number
  retard: number
  en_retard: number
  eleves: number
  pourcentage_exigible: number
}

export interface LigneFrais {
  id: number
  niveau_id: number
  annee_scolaire_id: number
  libelle: string
  montant: number
}

export interface Echeance {
  id?: number
  libelle: string
  date_limite: string
  pourcentage: number
}

export interface GrilleFrais {
  data: { niveau: { id: number; libelle: string }; frais: LigneFrais[]; total: number }[]
  meta: { annee: { id: number; libelle: string; date_debut: string; date_fin: string }; echeances: Echeance[] }
}
