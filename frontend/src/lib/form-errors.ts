import type { FieldValues, UseFormSetError } from 'react-hook-form'
import { toast } from 'sonner'
import { applyValidationErrors, getErrorMessage } from '@/lib/api'

/**
 * Erreur à la soumission d'un formulaire : les erreurs de validation (422) sont
 * affichées sous les champs, les autres (409, 500…) dans une notification.
 */
export function handleFormError<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>): void {
  if (!applyValidationErrors(error, setError)) {
    toast.error(getErrorMessage(error))
  }
}
