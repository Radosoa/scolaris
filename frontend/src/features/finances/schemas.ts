import { z } from 'zod'
import { deviseActive, lireMontant } from './format'

export const paiementSchema = z
  .object({
    montant: z.string().refine((v) => lireMontant(v) !== null, { error: () => messageMontant() }),
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
  montant: z.string().refine((v) => lireMontant(v) !== null, { error: () => messageMontant() }),
})

export type FraisValues = z.infer<typeof fraisSchema>

/** Message lu au moment de la validation : il suit la devise de l'établissement. */
function messageMontant(): string {
  return deviseActive().decimales > 0 ? 'Montant invalide (ex. 480 ou 480,50).' : 'Montant invalide (ex. 150000, sans décimales).'
}
