import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { PaginationMeta } from '@/types/api'

export function Pagination({ meta, onPageChange }: { meta: PaginationMeta; onPageChange: (page: number) => void }) {
  if (meta.total === 0) return null

  return (
    <div className="flex flex-col items-center justify-between gap-2 text-sm text-muted-foreground sm:flex-row">
      <p>
        {meta.from}–{meta.to} sur {meta.total}
      </p>
      {meta.last_page > 1 && (
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => onPageChange(meta.current_page - 1)}
            disabled={meta.current_page <= 1}
          >
            <ChevronLeft />
            Précédent
          </Button>
          <span className="tabular-nums">
            Page {meta.current_page} / {meta.last_page}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => onPageChange(meta.current_page + 1)}
            disabled={meta.current_page >= meta.last_page}
          >
            Suivant
            <ChevronRight />
          </Button>
        </div>
      )}
    </div>
  )
}
