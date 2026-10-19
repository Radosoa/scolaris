import { CircleHelp, Loader2, TriangleAlert } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogMedia,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { getErrorMessage } from '@/lib/api'

interface ConfirmDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description: string
  confirmLabel?: string
  destructive?: boolean
  /**
   * La modale affiche un indicateur de chargement tant que la promesse n'est pas résolue.
   * En cas d'échec, l'erreur est notifiée et la modale reste ouverte.
   */
  onConfirm: () => Promise<unknown>
}

export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel = 'Confirmer',
  destructive = false,
  onConfirm,
}: ConfirmDialogProps) {
  const [enCours, setEnCours] = useState(false)

  const confirmer = async () => {
    setEnCours(true)
    try {
      await onConfirm()
      onOpenChange(false)
    } catch (error) {
      // La modale reste ouverte pour réessayer ou annuler
      toast.error(getErrorMessage(error))
    } finally {
      setEnCours(false)
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={(ouvert) => !enCours && onOpenChange(ouvert)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogMedia
            className={
              destructive
                ? 'bg-red-50 text-red-600 ring-1 ring-red-100 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20'
                : 'bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20'
            }
          >
            {destructive ? <TriangleAlert /> : <CircleHelp />}
          </AlertDialogMedia>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={enCours}>Annuler</AlertDialogCancel>
          {/* Bouton simple plutôt qu'AlertDialogAction, qui fermerait la modale avant la fin de l'appel */}
          <Button variant={destructive ? 'destructive' : 'default'} onClick={confirmer} disabled={enCours}>
            {enCours && <Loader2 className="animate-spin" />}
            {confirmLabel}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
