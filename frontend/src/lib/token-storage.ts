const CLE = 'scolaris.token'

// localStorage peut être indisponible (navigation privée, stockage bloqué)
export const tokenStorage = {
  get(): string | null {
    try {
      return localStorage.getItem(CLE)
    } catch {
      return null
    }
  },
  set(token: string): void {
    try {
      localStorage.setItem(CLE, token)
    } catch {
      /* le token reste valable pour la session en mémoire */
    }
  },
  clear(): void {
    try {
      localStorage.removeItem(CLE)
    } catch {
      /* rien à nettoyer */
    }
  },
}
