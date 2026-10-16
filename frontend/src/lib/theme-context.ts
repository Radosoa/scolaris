import { createContext, useContext } from 'react'

export type Theme = 'light' | 'dark' | 'system'

export interface ThemeContextValue {
  /** Choix de l'utilisateur */
  theme: Theme
  /** Thème réellement appliqué (le système résolu) */
  resolvedTheme: 'light' | 'dark'
  setTheme: (theme: Theme) => void
}

/** Clé de stockage, partagée avec le script d'index.html qui applique le thème avant le rendu. */
export const CLE_THEME = 'scolaris.theme'

export const ThemeContext = createContext<ThemeContextValue | null>(null)

export function useTheme(): ThemeContextValue {
  const contexte = useContext(ThemeContext)
  if (!contexte) throw new Error('useTheme doit être utilisé dans un ThemeProvider')
  return contexte
}
