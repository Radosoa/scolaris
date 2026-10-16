export type CibleAnnonce = 'tous' | 'professeurs' | 'parents' | 'classe'

export const CIBLES: Record<CibleAnnonce, string> = {
  tous: 'Tout l’établissement',
  professeurs: 'Professeurs',
  parents: 'Parents',
  classe: 'Une classe',
}

export interface Annonce {
  id: number
  titre: string
  contenu: string
  cible: CibleAnnonce
  cible_libelle: string
  classe: { id: number; nom: string } | null
  auteur: string
  date_publication: string
  publiee: boolean
  modifiable: boolean
}

export interface AnnoncePayload {
  titre: string
  contenu: string
  cible: CibleAnnonce
  classe_id: number | null
  date_publication: string | null
}
