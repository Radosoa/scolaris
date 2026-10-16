import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { PaginationMeta } from '@/types/api'
import { pagesAffichees } from './pages'

export function Pagination({ meta, onPageChange }: { meta: PaginationMeta; onPageChange: (page: number) => void }) {
  if (meta.total === 0) return null

  return (
    <nav aria-label="Pagination" className="flex flex-col items-center justify-between gap-3 text-sm text-muted-foreground sm:flex-row">
      <p>
        <span className="font-medium text-foreground tabular-nums">
          {meta.from}–{meta.to}
        </span>{' '}
        sur <span className="font-medium text-foreground tabular-nums">{meta.total}</span>
      </p>
      {meta.last_page > 1 && (
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon-sm"
            onClick={() => onPageChange(meta.current_page - 1)}
            disabled={meta.current_page <= 1}
            aria-label="Page précédente"
          >
            <ChevronLeft />
          </Button>
          {pagesAffichees(meta.current_page, meta.last_page).map((page, i) =>
            page === '…' ? (
              <span key={`trou-${i}`} className="w-7 text-center" aria-hidden>
                …
              </span>
            ) : (
              <Button
                key={page}
                variant="ghost"
                size="icon-sm"
                onClick={() => onPageChange(page)}
                aria-label={`Page ${page}`}
                aria-current={page === meta.current_page ? 'page' : undefined}
                className={cn(
                  'tabular-nums',
                  page === meta.current_page && 'bg-indigo-50 font-semibold text-indigo-700 hover:bg-indigo-50 dark:bg-indigo-500/15 dark:text-indigo-200',
                )}
              >
                {page}
              </Button>
            ),
          )}
          <Button
            variant="ghost"
            size="icon-sm"
            onClick={() => onPageChange(meta.current_page + 1)}
            disabled={meta.current_page >= meta.last_page}
            aria-label="Page suivante"
          >
            <ChevronRight />
          </Button>
        </div>
      )}
    </nav>
  )
}
