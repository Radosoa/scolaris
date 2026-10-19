import { z } from 'zod'

export const loginSchema = z.object({
  ecole: z
    .string()
    .trim()
    .toLowerCase()
    .min(1, 'Indiquez le code de votre établissement.')
    .regex(/^[a-z0-9]+(-[a-z0-9]+)*$/, 'Code invalide (lettres, chiffres et tirets, ex. lycee-andohalo).'),
  email: z.email('Adresse e-mail invalide.'),
  password: z.string().min(1, 'Le mot de passe est obligatoire.'),
})

export type LoginValues = z.infer<typeof loginSchema>
