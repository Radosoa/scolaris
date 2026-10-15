import { z } from 'zod'

const date = (libelle: string) => z.string().min(1, `La ${libelle} est obligatoire.`)

/** Vérifie que la date de fin suit la date de début (format AAAA-MM-JJ, comparable en texte). */
const finApresDebut = <T extends { date_debut: string; date_fin: string }>(valeurs: T) =>
  !valeurs.date_debut || !valeurs.date_fin || valeurs.date_fin > valeurs.date_debut

export const anneeSchema = z
  .object({
    libelle: z
      .string()
      .regex(/^\d{4}-\d{4}$/, 'Format attendu : AAAA-AAAA (ex. 2025-2026).')
      .refine((libelle) => {
        const [debut, fin] = libelle.split('-').map(Number)
        return fin === debut + 1
      }, 'Le libellé doit couvrir deux années consécutives.'),
    date_debut: date('date de début'),
    date_fin: date('date de fin'),
    active: z.boolean(),
  })
  .refine(finApresDebut, { path: ['date_fin'], message: 'La date de fin doit suivre la date de début.' })

export type AnneeValues = z.infer<typeof anneeSchema>

export const periodeSchema = z
  .object({
    libelle: z.string().trim().min(1, 'Le libellé est obligatoire.').max(50),
    ordre: z.number({ error: 'L’ordre est obligatoire.' }).int().min(1).max(12),
    date_debut: date('date de début'),
    date_fin: date('date de fin'),
  })
  .refine(finApresDebut, { path: ['date_fin'], message: 'La date de fin doit suivre la date de début.' })

export type PeriodeValues = z.infer<typeof periodeSchema>

export const niveauSchema = z.object({
  libelle: z.string().trim().min(1, 'Le libellé est obligatoire.').max(50),
  ordre: z.number({ error: 'L’ordre est obligatoire.' }).int().min(1, 'Minimum 1.').max(99, 'Maximum 99.'),
})

export type NiveauValues = z.infer<typeof niveauSchema>

export const classeSchema = z.object({
  nom: z.string().trim().min(1, 'Le nom est obligatoire.').max(50),
  niveau_id: z.number({ error: 'Choisissez un niveau.' }),
  annee_scolaire_id: z.number({ error: 'Choisissez une année scolaire.' }),
  capacite: z
    .number({ error: 'La capacité est obligatoire.' })
    .int()
    .min(1, 'Minimum 1 élève.')
    .max(200, 'Maximum 200 élèves.'),
})

export type ClasseValues = z.infer<typeof classeSchema>

export const matiereSchema = z.object({
  nom: z.string().trim().min(1, 'Le nom est obligatoire.').max(100),
  code: z
    .string()
    .trim()
    .min(1, 'Le code est obligatoire.')
    .max(20)
    .regex(/^[A-Za-z0-9-]+$/, 'Lettres, chiffres et tirets uniquement.'),
})

export type MatiereValues = z.infer<typeof matiereSchema>
