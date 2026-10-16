import { z } from 'zod'

const telephone = z.string().regex(/^[0-9+().\s-]{6,30}$/, 'Numéro de téléphone invalide.')

export const eleveSchema = z.object({
  nom: z.string().trim().min(1, 'Le nom est obligatoire.').max(100),
  prenom: z.string().trim().min(1, 'Le prénom est obligatoire.').max(100),
  sexe: z.enum(['M', 'F'], { error: 'Choisissez le sexe.' }),
  date_naissance: z
    .string()
    .min(1, 'La date de naissance est obligatoire.')
    .refine((date) => date < new Date().toISOString().slice(0, 10), 'La date doit être dans le passé.'),
  lieu_naissance: z.string().trim().max(100).optional(),
})

export type EleveValues = z.infer<typeof eleveSchema>

export const tuteurSchema = z.object({
  nom: z.string().trim().min(1, 'Le nom est obligatoire.').max(100),
  prenom: z.string().trim().min(1, 'Le prénom est obligatoire.').max(100),
  telephone,
  email: z.union([z.literal(''), z.email('Adresse e-mail invalide.')]).optional(),
})

export type TuteurValues = z.infer<typeof tuteurSchema>

const motDePasse = z
  .string()
  .min(8, 'Au moins 8 caractères.')
  .regex(/[A-Za-z]/, 'Au moins une lettre.')
  .regex(/\d/, 'Au moins un chiffre.')

export const enseignantSchema = z.object({
  nom: z.string().trim().min(1, 'Le nom est obligatoire.').max(100),
  prenom: z.string().trim().min(1, 'Le prénom est obligatoire.').max(100),
  specialite: z.string().trim().max(100).optional(),
  telephone: z.union([z.literal(''), telephone]).optional(),
  email: z.email('Adresse e-mail invalide.'),
  // Exigé à la création uniquement (vérifié par le formulaire)
  password: z.string().optional(),
})

export type EnseignantValues = z.infer<typeof enseignantSchema>

export const compteSchema = z.object({
  email: z.email('Adresse e-mail invalide.'),
  password: motDePasse,
})

export type CompteValues = z.infer<typeof compteSchema>

export { motDePasse }
