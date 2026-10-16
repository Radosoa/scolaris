import { z } from 'zod'

export const MAX_APPRECIATION_GENERALE = 600
export const MAX_APPRECIATION_MATIERE = 300

export const appreciationGeneraleSchema = z.object({
  appreciation_generale: z
    .string()
    .trim()
    .max(MAX_APPRECIATION_GENERALE, `${MAX_APPRECIATION_GENERALE} caractères au maximum.`),
})

export type AppreciationGeneraleValues = z.infer<typeof appreciationGeneraleSchema>
