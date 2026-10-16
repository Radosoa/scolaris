import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { EmptyState } from '@/components/EmptyState'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn } from '@/lib/utils'
import type { PaginationMeta } from '@/types/api'
import { Pagination } from './Pagination'

export interface Column<T> {
  header: string
  cell: (ligne: T) => ReactNode
  className?: string
}

interface DataTableProps<T> {
  columns: Column<T>[]
  data: T[] | undefined
  isLoading?: boolean
  /** Rechargement en arrière-plan (changement de page ou de recherche) */
  isFetching?: boolean
  emptyMessage?: string
  /** Précision et icône de l'état vide */
  emptyDescription?: string
  emptyIcon?: LucideIcon
  /** Action proposée dans l'état vide (ex. « Nouvel élève ») */
  emptyAction?: ReactNode
  meta?: PaginationMeta
  onPageChange?: (page: number) => void
  rowKey?: (ligne: T) => string | number
}

/** Largeurs variées pour des lignes de chargement plus naturelles. */
const LARGEURS = ['max-w-40', 'max-w-24', 'max-w-32', 'max-w-20', 'max-w-28']

export function DataTable<T extends { id: number }>({
  columns,
  data,
  isLoading,
  isFetching,
  emptyMessage = 'Aucun résultat.',
  emptyDescription,
  emptyIcon,
  emptyAction,
  meta,
  onPageChange,
  rowKey = (ligne) => ligne.id,
}: DataTableProps<T>) {
  const vide = !isLoading && data?.length === 0

  return (
    <div className="space-y-3">
      <div
        className={cn(
          'overflow-hidden rounded-xl border bg-background shadow-xs transition-opacity',
          isFetching && !isLoading && 'opacity-60',
        )}
        aria-busy={isLoading || isFetching}
      >
        {vide ? (
          <EmptyState titre={emptyMessage} description={emptyDescription} icone={emptyIcon} action={emptyAction} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                {columns.map((colonne) => (
                  <TableHead key={colonne.header} className={colonne.className}>
                    {colonne.header}
                  </TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading &&
                Array.from({ length: 6 }, (_, i) => (
                  <TableRow key={i}>
                    {columns.map((colonne, j) => (
                      <TableCell key={colonne.header} className={colonne.className}>
                        <Skeleton className={cn('h-4 w-full', LARGEURS[(i + j) % LARGEURS.length])} />
                      </TableCell>
                    ))}
                  </TableRow>
                ))}

              {!isLoading &&
                data?.map((ligne) => (
                  <TableRow key={rowKey(ligne)}>
                    {columns.map((colonne) => (
                      <TableCell key={colonne.header} className={colonne.className}>
                        {colonne.cell(ligne)}
                      </TableCell>
                    ))}
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        )}
      </div>

      {meta && onPageChange && <Pagination meta={meta} onPageChange={onPageChange} />}
    </div>
  )
}
