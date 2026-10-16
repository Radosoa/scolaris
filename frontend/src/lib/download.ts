import axios from 'axios'
import { api } from './api'

/** Nom de fichier annoncé par l'en-tête Content-Disposition (exposé par la configuration CORS). */
function nomDepuisEntete(entete: string | undefined): string | null {
  const correspondance = entete?.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)
  return correspondance ? decodeURIComponent(correspondance[1]) : null
}

/**
 * Télécharge un fichier protégé par le token (les liens <a href> n'envoient pas l'en-tête
 * Authorization) : appel Axios en blob, puis lien temporaire.
 */
export async function telecharger(chemin: string, nomParDefaut: string): Promise<void> {
  const reponse = await api.get<Blob>(chemin, { responseType: 'blob' }).catch(async (error) => {
    // En mode blob, le corps JSON d'une erreur (403, 404…) arrive lui aussi en Blob
    if (axios.isAxiosError(error) && error.response?.data instanceof Blob) {
      try {
        error.response.data = JSON.parse(await error.response.data.text())
      } catch {
        // corps non JSON : message générique
      }
    }
    throw error
  })
  const url = URL.createObjectURL(reponse.data)
  const lien = document.createElement('a')
  lien.href = url
  lien.download = nomDepuisEntete(reponse.headers['content-disposition']) ?? nomParDefaut
  document.body.appendChild(lien)
  lien.click()
  lien.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
