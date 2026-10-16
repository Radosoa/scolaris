export type Role = 'admin' | 'secretariat' | 'professeur' | 'eleve' | 'parent'

export const ROLE_LIBELLES: Record<Role, string> = {
  admin: 'Direction',
  secretariat: 'Secrétariat',
  professeur: 'Professeur',
  eleve: 'Élève',
  parent: 'Parent',
}

export interface User {
  id: number
  name: string
  email: string
  actif: boolean
  roles: Role[]
  permissions: string[]
  derniere_connexion_at: string | null
  profil: {
    eleve_id: number | null
    tuteur_id: number | null
    enseignant_id: number | null
  }
}

export interface LoginPayload {
  email: string
  password: string
}

export interface LoginResponse {
  token: string
  token_type: 'Bearer'
  user: User
}
