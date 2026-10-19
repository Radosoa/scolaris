import { describe, expect, it } from 'vitest'
import { libelleVariation, tonVariation, variation } from './tendances'

describe('variation', () => {
  it('calcule l’écart relatif arrondi', () => {
    expect(variation(110, 100)).toEqual({ pourcentage: 10, sens: 'hausse' })
    expect(variation(75, 100)).toEqual({ pourcentage: -25, sens: 'baisse' })
    expect(variation(100.4, 100)).toEqual({ pourcentage: 0, sens: 'stable' })
  })

  it('gère une référence nulle', () => {
    expect(variation(5, 0)).toEqual({ pourcentage: null, sens: 'hausse' })
    expect(variation(0, 0)).toEqual({ pourcentage: null, sens: 'stable' })
  })
})

describe('tonVariation', () => {
  it('dépend du sens favorable de l’indicateur', () => {
    expect(tonVariation('hausse', true)).toBe('positif')
    expect(tonVariation('hausse', false)).toBe('negatif')
    expect(tonVariation('baisse', false)).toBe('positif')
    expect(tonVariation('stable', true)).toBe('neutre')
  })
})

describe('libelleVariation', () => {
  it('affiche un signe et le pourcentage', () => {
    expect(libelleVariation({ pourcentage: 12, sens: 'hausse' })).toBe('+12 %')
    expect(libelleVariation({ pourcentage: -8, sens: 'baisse' })).toBe('−8 %')
    expect(libelleVariation({ pourcentage: 0, sens: 'stable' })).toBe('=')
    expect(libelleVariation({ pourcentage: null, sens: 'hausse' })).toBe('nouveau')
  })
})
