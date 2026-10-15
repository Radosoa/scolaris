import type { ReactNode } from 'react'
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
  meta?: PaginationMeta
  onPageChange?: (page: number) => void
  rowKey?: (ligne: T) => string | number
}

export function DataTable<T extends { id: number }>({
  columns,
  data,
  isLoading,
  isFetching,
  emptyMessage = 'Aucun résultat.',
  meta,
  onPageChange,
  rowKey = (ligne) => ligne.id,
}: DataTableProps<T>) {
  return (
    <div className="space-y-3">
      <div className={cn('rounded-lg border bg-background transition-opacity', isFetching && !isLoading && 'opacity-60')}>
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((colonne) => (
                <TableHead key={colonne.header} className={colonne.className}>
                  {colonne.header}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 5 }, (_, i) => (
                <TableRow key={i}>
                  {columns.map((colonne) => (
                    <TableCell key={colonne.header}>
                      <Skeleton className="h-4 w-full max-w-32" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && data?.length === 0 && (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                  {emptyMessage}
                </TableCell>
              </TableRow>
            )}

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
      </div>

      {meta && onPageChange && <Pagination meta={meta} onPageChange={onPageChange} />}
    </div>
  )
}
