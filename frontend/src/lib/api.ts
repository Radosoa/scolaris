import axios, { AxiosError } from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ecoleStorage } from './ecole'
import { tokenStorage } from './token-storage'

/** Événement émis quand l'API répond 401 : l'AuthProvider déconnecte l'utilisateur. */
export const UNAUTHORIZED_EVENT = 'scolaris:unauthorized'

export const api = axios.create({
  baseURL: `${import.meta.env.VITE_API_URL ?? 'http://localhost:8001'}/api`,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Multi-établissement : chaque requête désigne la base de son école
  const ecole = ecoleStorage.get()
  if (ecole) {
    config.headers['X-Ecole'] = ecole
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ errors?: Record<string, string[]> }>) => {
    const horsConnexion = !error.config?.url?.endsWith('/auth/login')
    // École absente ou inconnue (session ouverte avant le multi-établissement) : retour à la connexion
    const ecoleManquante = error.response?.status === 422 && !!error.response.data?.errors?.ecole
    // Un 401 sur /auth/login signifie « identifiants invalides », pas « session expirée »
    if ((error.response?.status === 401 || ecoleManquante) && horsConnexion) {
      tokenStorage.clear()
      window.dispatchEvent(new Event(UNAUTHORIZED_EVENT))
    }
    return Promise.reject(error)
  },
)

interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/** Message lisible à afficher à l'utilisateur pour une erreur d'appel API. */
export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError<ApiErrorBody>(error)) {
    if (!error.response) {
      return 'Impossible de joindre le serveur. Vérifiez votre connexion.'
    }
    if (error.response.status === 429) {
      return 'Trop de tentatives. Patientez une minute avant de réessayer.'
    }
    return error.response.data?.message ?? 'Une erreur inattendue est survenue.'
  }
  return 'Une erreur inattendue est survenue.'
}

/**
 * Reporte les erreurs de validation Laravel (422) sur les champs du formulaire.
 * Renvoie true si au moins une erreur a été appliquée.
 */
export function applyValidationErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
): boolean {
  if (!axios.isAxiosError<ApiErrorBody>(error) || error.response?.status !== 422) {
    return false
  }
  const errors = error.response.data?.errors ?? {}
  Object.entries(errors).forEach(([champ, messages]) => {
    setError(champ as Path<T>, { type: 'server', message: messages[0] })
  })
  return Object.keys(errors).length > 0
}
