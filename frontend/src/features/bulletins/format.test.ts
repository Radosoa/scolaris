import { describe, expect, it } from 'vitest'
import { formatHeures, formatMoyenne, formatRang, rangsExAequo, tauxReussite, tonMoyenne } from './format'

describe('formatMoyenne', () => {
  it('affiche toujours deux décimales avec une virgule', () => {
    expect(formatMoyenne(13.6)).toBe('13,60')
    expect(formatMoyenne(8)).toBe('8,00')
    expect(formatMoyenne(null)).toBe('—')
  })
})

describe('formatRang', () => {
  it('écrit 1er, puis 2e, 3e…', () => {
    expect(formatRang(1)).toBe('1er')
    expect(formatRang(2)).toBe('2e')
    expect(formatRang(24)).toBe('24e')
  })

  it('signale les ex æquo et les élèves non classés', () => {
    expect(formatRang(2, true)).toBe('2e ex æquo')
    expect(formatRang(null)).toBe('Non classé')
  })
})

describe('rangsExAequo', () => {
  it('repère les rangs partagés (1, 2, 2, 4)', () => {
    expect(rangsExAequo([{ rang: 1 }, { rang: 2 }, { rang: 2 }, { rang: 4 }, { rang: null }, { rang: null }])).toEqual(
      new Set([2]),
    )
  })
})

describe('tauxReussite', () => {
  it('compte les moyennes ≥ 10 parmi les élèves classés', () => {
    expect(tauxReussite([{ moyenne: 12 }, { moyenne: 10 }, { moyenne: 9.99 }, { moyenne: 4 }, { moyenne: null }])).toBe(0.5)
    expect(tauxReussite([{ moyenne: null }])).toBeNull()
  })
})

describe('formatHeures', () => {
  it('affiche des heures décimales', () => {
    expect(formatHeures(0)).toBe('0 h')
    expect(formatHeures(1.5)).toBe('1,5 h')
  })
})

describe('tonMoyenne', () => {
  it('suit les seuils des mentions', () => {
    expect(tonMoyenne(15)).toBe('excellent')
    expect(tonMoyenne(12)).toBe('bon')
    expect(tonMoyenne(10)).toBe('moyen')
    expect(tonMoyenne(9.99)).toBe('faible')
    expect(tonMoyenne(null)).toBeNull()
  })
})
