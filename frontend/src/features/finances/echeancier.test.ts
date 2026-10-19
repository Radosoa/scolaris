import { describe, expect, it } from 'vitest'
import { echeancierMensuel } from './echeancier'
import { totalPourcentages } from './format'

describe('echeancierMensuel', () => {
  it('crée une tranche par mois, avant le 5, de septembre à juin', () => {
    const tranches = echeancierMensuel('2026-09-01', '2027-06-30')
    expect(tranches).toHaveLength(10)
    expect(tranches[0]).toEqual({ libelle: 'Septembre 2026', date_limite: '2026-09-05', pourcentage: 10 })
    expect(tranches[4]).toEqual({ libelle: 'Janvier 2027', date_limite: '2027-01-05', pourcentage: 10 })
    expect(tranches[9].libelle).toBe('Juin 2027')
    expect(totalPourcentages(tranches)).toBe(100)
  })

  it('commence à la rentrée, ignore un dernier mois trop court et répartit l’arrondi', () => {
    const tranches = echeancierMensuel('2026-10-12', '2027-05-03')
    // Octobre à avril : mai se termine le 3, avant la date limite du 5
    expect(tranches).toHaveLength(7)
    // Rentrée le 12 octobre : la première échéance ne peut pas la précéder
    expect(tranches[0].date_limite).toBe('2026-10-12')
    expect(tranches[6]).toEqual({ libelle: 'Avril 2027', date_limite: '2027-04-05', pourcentage: 14.32 })
    expect(tranches[0].pourcentage).toBe(14.28)
    expect(echeancierMensuel('2026-09-01', '2027-07-04')).toHaveLength(10)
    expect(totalPourcentages(tranches)).toBe(100)
  })
})
