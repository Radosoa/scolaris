import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { cn } from '@/lib/utils'
import type { Eleve } from '../types'

function initiales(prenom: string, nom: string): string {
  return `${prenom[0] ?? ''}${nom[0] ?? ''}`.toUpperCase()
}

export function EleveAvatar({ eleve, className }: { eleve: Pick<Eleve, 'prenom' | 'nom' | 'photo_url'>; className?: string }) {
  return (
    <Avatar className={cn('size-8', className)}>
      {eleve.photo_url && <AvatarImage src={eleve.photo_url} alt="" className="object-cover" />}
      <AvatarFallback className="text-xs">{initiales(eleve.prenom, eleve.nom)}</AvatarFallback>
    </Avatar>
  )
}
