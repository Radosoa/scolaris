/** Métadonnées de pagination renvoyées par Laravel (ResourceCollection paginée). */
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

/** Paramètres de liste communs : page, taille, recherche et filtres libres. */
export type ListParams = {
  page?: number
  per_page?: number
  q?: string
} & Record<string, string | number | undefined>
