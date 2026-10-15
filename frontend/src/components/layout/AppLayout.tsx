import { Menu } from 'lucide-react'
import { useState } from 'react'
import { Outlet } from 'react-router'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { SidebarNav } from './SidebarNav'
import { UserMenu } from './UserMenu'

export function AppLayout() {
  const [menuMobileOuvert, setMenuMobileOuvert] = useState(false)

  return (
    <div className="min-h-svh bg-muted/30">
      {/* Sidebar fixe sur grand écran */}
      <aside className="fixed inset-y-0 left-0 hidden w-64 border-r bg-sidebar lg:block">
        <SidebarNav />
      </aside>

      {/* Tiroir sur mobile et tablette */}
      <Sheet open={menuMobileOuvert} onOpenChange={setMenuMobileOuvert}>
        <SheetContent side="left" className="w-64 bg-sidebar p-0">
          <SheetTitle className="sr-only">Navigation</SheetTitle>
          <SheetDescription className="sr-only">Menu principal de l'application</SheetDescription>
          <SidebarNav onNavigate={() => setMenuMobileOuvert(false)} />
        </SheetContent>
      </Sheet>

      <div className="lg:pl-64">
        <header className="sticky top-0 z-10 flex h-14 items-center gap-2 border-b bg-background/95 px-4 backdrop-blur">
          <Button
            variant="ghost"
            size="icon"
            className="lg:hidden"
            onClick={() => setMenuMobileOuvert(true)}
            aria-label="Ouvrir le menu"
          >
            <Menu />
          </Button>
          <div className="ml-auto">
            <UserMenu />
          </div>
        </header>

        <main className="mx-auto w-full max-w-7xl p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
