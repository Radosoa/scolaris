import { z } from 'zod'
import { lireMontant } from './format'

export const paiementSchema = z
  .object({
    montant: z.string().refine((v) => lireMontant(v) !== null, 'Montant invalide (ex. 480 ou 480,50).'),
    date: z.string().min(1, 'La date est obligatoire.'),
    mode: z.enum(['especes', 'cheque', 'virement', 'carte'], { error: 'Choisissez le mode de paiement.' }),
    reference: z.string().trim().max(100, '100 caractères au maximum.'),
  })
  .refine((v) => v.mode === 'especes' || v.reference.length > 0, {
    path: ['reference'],
    message: 'Indiquez la référence (n° de chèque, de virement ou de transaction).',
  })

export type PaiementValues = z.infer<typeof paiementSchema>

export const fraisSchema = z.object({
  libelle: z.string().trim().min(1, 'Le libellé est obligatoire.').max(100),
  montant: z.string().refine((v) => lireMontant(v) !== null, 'Montant invalide.'),
})

export type FraisValues = z.infer<typeof fraisSchema>
