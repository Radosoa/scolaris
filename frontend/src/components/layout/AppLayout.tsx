import { CalendarDays, Menu } from 'lucide-react'
import { useState } from 'react'
import { Outlet } from 'react-router'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useParametres } from '@/features/etablissement/api'
import { ChoixTheme } from './ChoixTheme'
import { RechercheRapide } from './RechercheRapide'
import { SidebarNav } from './SidebarNav'

export function AppLayout() {
  const [menuMobileOuvert, setMenuMobileOuvert] = useState(false)
  // La devise doit être connue avant d'afficher le moindre montant
  const parametres = useParametres()
  // Date figée au montage (pas d'appel impur pendant le rendu)
  const [aujourdhui] = useState(() =>
    new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date()),
  )

  return (
    <div className="min-h-svh bg-slate-50/80 dark:bg-background">
      {/* Sidebar fixe sur grand écran */}
      <aside className="fixed inset-y-0 left-0 hidden w-64 border-r bg-sidebar lg:block">
        <SidebarNav />
      </aside>

      {/* Tiroir sur mobile et tablette */}
      <Sheet open={menuMobileOuvert} onOpenChange={setMenuMobileOuvert}>
        <SheetContent side="left" className="w-72 bg-sidebar p-0">
          <SheetTitle className="sr-only">Navigation</SheetTitle>
          <SheetDescription className="sr-only">Menu principal de l'application</SheetDescription>
          <SidebarNav onNavigate={() => setMenuMobileOuvert(false)} />
        </SheetContent>
      </Sheet>

      <div className="lg:pl-64">
        <header className="sticky top-0 z-10 flex h-16 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur-md sm:px-6">
          <Button
            variant="ghost"
            size="icon"
            className="lg:hidden"
            onClick={() => setMenuMobileOuvert(true)}
            aria-label="Ouvrir le menu"
          >
            <Menu />
          </Button>
          <RechercheRapide />
          <p className="ml-auto hidden items-center gap-2 text-sm text-muted-foreground md:flex">
            <CalendarDays className="size-4" />
            <span className="first-letter:uppercase">{aujourdhui}</span>
          </p>
          <ChoixTheme className="ml-auto md:ml-0" />
        </header>

        <main className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
          {parametres.isPending ? (
            <Skeleton className="h-96 w-full" />
          ) : (
            // Remonté à chaque changement de devise : tous les montants sont réaffichés
            <Outlet key={parametres.data?.devise.code} />
          )}
        </main>
      </div>
    </div>
  )
}
