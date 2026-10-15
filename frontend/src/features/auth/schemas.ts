import { z } from 'zod'

export const loginSchema = z.object({
  email: z.email('Adresse e-mail invalide.'),
  password: z.string().min(1, 'Le mot de passe est obligatoire.'),
})

export type LoginValues = z.infer<typeof loginSchema>
