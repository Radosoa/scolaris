import { Bell, BellOff, CheckCheck, TriangleAlert, Wallet } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { useLireNotification, useNotifications, useToutLire, type NotificationApp } from '@/features/notifications/api'
import { ilYA } from '@/features/notifications/temps'
import { cn } from '@/lib/utils'

const ICONES: Record<NonNullable<NotificationApp['type']>, { icone: typeof Bell; classe: string }> = {
  retard_ecolage: { icone: Wallet, classe: 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' },
  recap_retards: { icone: TriangleAlert, classe: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' },
}

/** Cloche de l'en-tête : avertissements (retards d'écolage…) avec pastille du nombre de non lues. */
export function Notifications() {
  const requete = useNotifications()
  const lire = useLireNotification()
  const toutLire = useToutLire()
  const navigate = useNavigate()
  // Heure de référence fixée à l'ouverture du menu (pas d'appel impur pendant le rendu)
  const [maintenant, setMaintenant] = useState(0)

  const notifications = requete.data?.data ?? []
  const nonLues = requete.data?.meta.non_lues ?? 0

  const ouvrir = (n: NotificationApp) => {
    if (!n.lu) lire.mutate(n.id)
    if (n.lien) navigate(n.lien)
  }

  return (
    <DropdownMenu onOpenChange={(ouvert) => ouvert && setMaintenant(Date.now())}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative" aria-label={nonLues ? `Notifications (${nonLues} non lues)` : 'Notifications'} title="Notifications">
          <Bell />
          {nonLues > 0 && (
            <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white ring-2 ring-background tabular-nums">
              {nonLues > 9 ? '9+' : nonLues}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-[min(24rem,calc(100vw-2rem))] p-0">
        <div className="flex items-center justify-between border-b px-4 py-3">
          <p className="text-sm font-semibold">Notifications</p>
          {nonLues > 0 && (
            <button
              type="button"
              onClick={() => toutLire.mutate()}
              className="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline dark:text-indigo-300"
            >
              <CheckCheck className="size-3.5" />
              Tout marquer comme lu
            </button>
          )}
        </div>

        {notifications.length === 0 ? (
          <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
            <BellOff className="size-5 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">Aucune notification</p>
          </div>
        ) : (
          <div className="max-h-[26rem] overflow-y-auto p-1.5">
            {notifications.map((n) => {
              const { icone: Icone, classe } = (n.type && ICONES[n.type]) || { icone: Bell, classe: 'bg-muted text-muted-foreground' }
              return (
                <DropdownMenuItem key={n.id} onSelect={() => ouvrir(n)} className="items-start gap-3 rounded-lg px-2.5 py-2.5">
                  <span className={cn('mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg', classe)}>
                    <Icone className="size-4" />
                  </span>
                  <span className="min-w-0 flex-1 space-y-0.5">
                    <span className={cn('block text-sm leading-snug', n.lu ? 'text-muted-foreground' : 'font-medium')}>{n.titre}</span>
                    <span className="line-clamp-2 block text-xs text-muted-foreground">{n.message}</span>
                    <span className="block text-[11px] text-muted-foreground/80">{maintenant ? ilYA(n.cree_le, maintenant) : ''}</span>
                  </span>
                  {!n.lu && <span aria-label="Non lue" className="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-600 dark:bg-indigo-400" />}
                </DropdownMenuItem>
              )
            })}
          </div>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
