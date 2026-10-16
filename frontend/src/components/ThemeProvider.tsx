import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { CLE_THEME, ThemeContext, type Theme } from '@/lib/theme-context'

const REQUETE_SOMBRE = '(prefers-color-scheme: dark)'

function themeMemorise(): Theme {
  try {
    const valeur = localStorage.getItem(CLE_THEME)
    return valeur === 'light' || valeur === 'dark' || valeur === 'system' ? valeur : 'system'
  } catch {
    // Stockage indisponible (navigation privée, cookies bloqués) : on suit le système
    return 'system'
  }
}

/**
 * Thème clair, sombre ou celui du système. Le choix est mémorisé dans le navigateur ;
 * la classe `dark` de <html> est posée dès le chargement par le script d'index.html.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(themeMemorise)
  const [systemeSombre, setSystemeSombre] = useState(() => window.matchMedia(REQUETE_SOMBRE).matches)

  useEffect(() => {
    const requete = window.matchMedia(REQUETE_SOMBRE)
    const changer = (event: MediaQueryListEvent) => setSystemeSombre(event.matches)
    requete.addEventListener('change', changer)
    return () => requete.removeEventListener('change', changer)
  }, [])

  const resolvedTheme = theme === 'system' ? (systemeSombre ? 'dark' : 'light') : theme

  useEffect(() => {
    document.documentElement.classList.toggle('dark', resolvedTheme === 'dark')
  }, [resolvedTheme])

  const setTheme = useCallback((suivant: Theme) => {
    setThemeState(suivant)
    try {
      localStorage.setItem(CLE_THEME, suivant)
    } catch {
      // Le thème s'applique quand même pour la session en cours
    }
  }, [])

  const valeur = useMemo(() => ({ theme, resolvedTheme, setTheme }), [theme, resolvedTheme, setTheme])

  return <ThemeContext.Provider value={valeur}>{children}</ThemeContext.Provider>
}
