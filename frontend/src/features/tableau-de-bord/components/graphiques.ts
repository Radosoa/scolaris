/** Couleurs des graphiques (jetons du thème, clair et sombre). */
export const COULEURS = {
  principale: 'var(--chart-1)',
  vert: 'var(--chart-2)',
  ambre: 'var(--chart-3)',
  rouge: 'var(--chart-4)',
  violet: 'var(--chart-5)',
}

/** Style commun des infobulles Recharts, aux couleurs du thème. */
export const INFOBULLE = {
  contentStyle: {
    background: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: 8,
    color: 'var(--popover-foreground)',
    fontSize: 12,
  },
  cursor: { fill: 'var(--muted)', opacity: 0.5 },
}
