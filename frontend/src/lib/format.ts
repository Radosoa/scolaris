/**
 * « 2025-09-01 » → « 01/09/2025 ». Découpe la chaîne plutôt que de passer par Date
 * pour éviter tout décalage de fuseau horaire.
 */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—'
  const [annee, mois, jour] = iso.slice(0, 10).split('-')
  return `${jour}/${mois}/${annee}`
}

const nombre = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 })

export function formatNombre(valeur: number | null | undefined): string {
  return valeur === null || valeur === undefined ? '—' : nombre.format(valeur)
}
