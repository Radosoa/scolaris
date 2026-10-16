import { z } from 'zod'
import { heureDecimale } from './emploi-du-temps'

const heure = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Format attendu : HH:MM.')

export const creneauSchema = z
  .object({
    affectation_id: z.number({ error: 'Choisissez la matière et le professeur.' }).int().positive('Choisissez la matière et le professeur.'),
    jour: z.number({ error: 'Choisissez un jour.' }).int().min(1).max(6),
    heure_debut: heure.refine((h) => heureDecimale(h) >= 7, 'Les cours commencent au plus tôt à 07:00.'),
    heure_fin: heure.refine((h) => heureDecimale(h) <= 19, 'Les cours finissent au plus tard à 19:00.'),
    salle: z.string().trim().max(50, '50 caractères au maximum.'),
  })
  .refine((v) => heureDecimale(v.heure_fin) > heureDecimale(v.heure_debut), {
    path: ['heure_fin'],
    message: 'L’heure de fin doit suivre l’heure de début.',
  })

export type CreneauValues = z.infer<typeof creneauSchema>

export const justificationSchema = z
  .object({
    justifiee: z.boolean(),
    motif: z.string().trim().max(255, '255 caractères au maximum.'),
    toute_la_journee: z.boolean(),
  })
  .refine((v) => !v.justifiee || v.motif.length > 0, { path: ['motif'], message: 'Indiquez le motif de l’absence.' })

export type JustificationValues = z.infer<typeof justificationSchema>

export const MOTIFS = ['Maladie', 'Rendez-vous médical', 'Certificat médical', 'Raison familiale', 'Retard de transport', 'Convocation administrative']
