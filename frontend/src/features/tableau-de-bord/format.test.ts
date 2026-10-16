import { describe, expect, it } from 'vitest'
import { formatPourcentage, libelleMois, libelleSemaine, remplissage } from './format'

describe('libellés des axes', () => {
  it('formate les mois et les semaines', () => {
    expect(libelleMois('2026-09')).toBe('sept. 2026')
    expect(libelleMois('2027-01')).toBe('janv. 2027')
    expect(libelleSemaine('2026-10-12')).toBe('12/10')
  })
})

describe('remplissage', () => {
  it('donne le taux d’occupation de la classe', () => {
    expect(remplissage(24, 32)).toBe(75)
    expect(remplissage(5, 0)).toBe(0)
  })
})

describe('formatPourcentage', () => {
  it('affiche une décimale au plus, à la française', () => {
    expect(formatPourcentage(16.67)).toBe('16,7 %')
    expect(formatPourcentage(50)).toBe('50 %')
    expect(formatPourcentage(null)).toBe('—')
  })
})
