const relatif = new Intl.RelativeTimeFormat('fr-FR', { numeric: 'auto' })

/** « à l'instant », « il y a 5 minutes », « hier », « il y a 3 jours » ; au-delà d'une semaine, la date. */
export function ilYA(iso: string, maintenant: number): string {
  const secondes = Math.round((new Date(iso).getTime() - maintenant) / 1000)
  const absolu = Math.abs(secondes)
  if (absolu < 60) return 'à l’instant'
  if (absolu < 3600) return relatif.format(Math.round(secondes / 60), 'minute')
  if (absolu < 86_400) return relatif.format(Math.round(secondes / 3600), 'hour')
  if (absolu < 7 * 86_400) return relatif.format(Math.round(secondes / 86_400), 'day')
  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(iso))
}
