import { describe, expect, it } from 'vitest'
import { formatEuros, lireMontant, progression, totalPourcentages } from './format'

describe('formatEuros', () => {
  it('affiche les montants à la française', () => {
    // Intl insère des espaces insécables : on les normalise pour comparer
    expect(formatEuros(1250.5).replace(/\s/g, ' ')).toBe('1 250,50 €')
    expect(formatEuros(0).replace(/\s/g, ' ')).toBe('0,00 €')
    expect(formatEuros(null)).toBe('—')
  })
})

describe('lireMontant', () => {
  it('accepte virgule, point, espaces et symbole euro', () => {
    expect(lireMontant('1 250,50')).toBe(1250.5)
    expect(lireMontant('1250.5')).toBe(1250.5)
    expect(lireMontant('300 €')).toBe(300)
    expect(lireMontant('1 000')).toBe(1000)
  })

  it('refuse les montants invalides, nuls ou à plus de deux décimales', () => {
    expect(lireMontant('')).toBeNull()
    expect(lireMontant('0')).toBeNull()
    expect(lireMontant('-5')).toBeNull()
    expect(lireMontant('12,345')).toBeNull()
    expect(lireMontant('douze')).toBeNull()
  })
})

describe('progression', () => {
  it('donne la part réglée en pourcentage, plafonnée à 100', () => {
    expect(progression(500, 1250)).toBe(40)
    expect(progression(0, 0)).toBe(0)
    expect(progression(2000, 1000)).toBe(100)
  })
})

describe('totalPourcentages', () => {
  it('additionne sans erreur de flottant', () => {
    expect(totalPourcentages([{ pourcentage: 33.33 }, { pourcentage: 33.33 }, { pourcentage: 33.34 }])).toBe(100)
    expect(totalPourcentages([{ pourcentage: 40 }, { pourcentage: Number.NaN }])).toBe(40)
  })
})
