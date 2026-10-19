import { Loader2, type LucideIcon } from 'lucide-react'
import type { FormEventHandler, ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

interface FormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description?: string
  onSubmit: FormEventHandler<HTMLFormElement>
  isSubmitting?: boolean
  submitLabel?: string
  /** Icône de l'en-tête (pastille teintée à gauche du titre) */
  icone?: LucideIcon
  children: ReactNode
}

/** Modale contenant un formulaire, avec boutons Annuler / Enregistrer. */
export function FormDialog({
  open,
  onOpenChange,
  title,
  description,
  onSubmit,
  isSubmitting = false,
  submitLabel = 'Enregistrer',
  icone: Icone,
  children,
}: FormDialogProps) {
  return (
    <Dialog open={open} onOpenChange={(ouvert) => !isSubmitting && onOpenChange(ouvert)}>
      <DialogContent className="sm:max-w-lg">
        <form onSubmit={onSubmit} noValidate className="space-y-5">
          <div className={cn('flex gap-4', description ? 'items-start' : 'items-center')}>
            {Icone && (
              <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20">
                <Icone className="size-5" />
              </span>
            )}
            <DialogHeader className="min-w-0 flex-1">
              <DialogTitle>{title}</DialogTitle>
              {description ? (
                <DialogDescription>{description}</DialogDescription>
              ) : (
                <DialogDescription className="sr-only">{title}</DialogDescription>
              )}
            </DialogHeader>
          </div>

          <div className="space-y-4">{children}</div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
              Annuler
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="animate-spin" />}
              {submitLabel}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
