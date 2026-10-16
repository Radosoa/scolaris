import { CornerDownLeft, GraduationCap, Search } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'
import { navigationPour } from '@/config/navigation'
import { useAuth } from '@/features/auth/auth-context'
import { eleves } from '@/features/personnes/api'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { cn } from '@/lib/utils'
import { deplacer, filtrerPages } from './recherche'

interface Resultat {
  cle: string
  libelle: string
  detail: string
  chemin: string
  icone: typeof GraduationCap
}

/**
 * Recherche rapide (Ctrl + K) : écrans accessibles à l'utilisateur et, pour ceux qui
 * consultent les dossiers, élèves par nom ou matricule (filtrés par l'API selon les droits).
 */
export function RechercheRapide() {
  const { user, hasRole } = useAuth()
  const navigate = useNavigate()
  const [ouvert, setOuvert] = useState(false)
  const [saisie, setSaisie] = useState('')
  const [actif, setActif] = useState(0)
  const terme = useDebouncedValue(saisie.trim())
  const chercheEleves = hasRole('admin', 'secretariat', 'professeur') && terme.length >= 2

  const listeEleves = eleves.useList({ q: terme, per_page: 5 }, { enabled: ouvert && chercheEleves })

  useEffect(() => {
    const raccourci = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        setOuvert((o) => !o)
      }
    }
    window.addEventListener('keydown', raccourci)
    return () => window.removeEventListener('keydown', raccourci)
  }, [])

  const resultats = useMemo<Resultat[]>(() => {
    const pages = filtrerPages(navigationPour(user?.roles ?? []), saisie).map((p) => ({
      cle: p.path,
      libelle: p.label,
      detail: p.groupe ?? 'Accueil',
      chemin: p.path,
      icone: p.icon,
    }))
    const trouves = chercheEleves
      ? (listeEleves.data?.data ?? []).map((e) => ({
          cle: `eleve-${e.id}`,
          libelle: e.nom_complet,
          detail: `Élève · ${e.matricule}${e.inscription_active?.classe ? ` · ${e.inscription_active.classe.nom}` : ''}`,
          chemin: `/eleves/${e.id}`,
          icone: GraduationCap,
        }))
      : []
    return [...pages, ...trouves]
  }, [user?.roles, saisie, chercheEleves, listeEleves.data])

  const changerOuverture = (valeur: boolean) => {
    setOuvert(valeur)
    if (!valeur) {
      setSaisie('')
      setActif(0)
    }
  }

  const aller = (resultat: Resultat | undefined) => {
    if (!resultat) return
    changerOuverture(false)
    navigate(resultat.chemin)
  }

  const nombrePages = resultats.findIndex((r) => r.cle.startsWith('eleve-'))

  return (
    <>
      <button
        type="button"
        onClick={() => setOuvert(true)}
        className="flex h-9 w-full max-w-md items-center gap-2 rounded-lg border bg-muted/40 px-3 text-sm text-muted-foreground transition-colors hover:bg-muted"
      >
        <Search className="size-4" />
        <span className="flex-1 text-left">Rechercher…</span>
        <kbd className="hidden rounded border bg-background px-1.5 py-0.5 font-sans text-[11px] sm:inline">Ctrl K</kbd>
      </button>

      <Dialog open={ouvert} onOpenChange={changerOuverture}>
        <DialogContent className="top-[20%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-lg" showCloseButton={false}>
          <DialogTitle className="sr-only">Recherche rapide</DialogTitle>
          <DialogDescription className="sr-only">Aller à un écran ou retrouver un élève</DialogDescription>
          <div className="flex items-center gap-2 border-b px-3">
            <Search className="size-4 text-muted-foreground" />
            <input
              autoFocus
              value={saisie}
              onChange={(e) => {
                setSaisie(e.target.value)
                setActif(0)
              }}
              onKeyDown={(e) => {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                  e.preventDefault()
                  setActif((i) => deplacer(i, e.key === 'ArrowDown' ? 1 : -1, resultats.length))
                } else if (e.key === 'Enter') {
                  e.preventDefault()
                  aller(resultats[actif])
                }
              }}
              placeholder={hasRole('admin', 'secretariat', 'professeur') ? 'Un écran, un élève, un matricule…' : 'Aller à un écran…'}
              aria-label="Rechercher"
              aria-controls="resultats-recherche"
              aria-activedescendant={resultats[actif] ? `resultat-${resultats[actif].cle}` : undefined}
              className="h-12 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            />
          </div>

          <ul id="resultats-recherche" role="listbox" className="max-h-80 overflow-y-auto p-2">
            {resultats.length === 0 && (
              <li className="px-3 py-6 text-center text-sm text-muted-foreground">
                {listeEleves.isFetching ? 'Recherche…' : 'Aucun résultat.'}
              </li>
            )}
            {resultats.map((r, i) => (
              <li key={r.cle} id={`resultat-${r.cle}`} role="option" aria-selected={i === actif}>
                {i === nombrePages && <p className="px-2 pt-2 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">Élèves</p>}
                <button
                  type="button"
                  onMouseMove={() => setActif(i)}
                  onClick={() => aller(r)}
                  className={cn('flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm', i === actif && 'bg-indigo-50 text-indigo-950 dark:bg-indigo-950/40 dark:text-indigo-100')}
                >
                  <span className={cn('flex size-8 items-center justify-center rounded-lg border bg-background', i === actif && 'border-indigo-200 text-indigo-600')}>
                    <r.icone className="size-4" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">{r.libelle}</span>
                    <span className="block truncate text-xs text-muted-foreground">{r.detail}</span>
                  </span>
                  {i === actif && <CornerDownLeft className="size-3.5 text-muted-foreground" />}
                </button>
              </li>
            ))}
          </ul>
          <div className="flex gap-4 border-t bg-muted/40 px-4 py-2 text-[11px] text-muted-foreground">
            <span>↑ ↓ naviguer</span>
            <span>Entrée ouvrir</span>
            <span>Échap fermer</span>
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}
