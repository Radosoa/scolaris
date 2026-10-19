import { describe, expect, it } from 'vitest'
import { formatMontant, formatMontantCompact, lireMontant, montantEnSaisie, progression, totalPourcentages, type Devise } from './format'

const ARIARY: Devise = { code: 'MGA', libelle: 'Ariary malgache', symbole: 'Ar', decimales: 0 }
const DOLLAR: Devise = { code: 'USD', libelle: 'Dollar américain', symbole: '$', decimales: 2 }
// Intl insère des espaces insécables : on les normalise pour comparer
const texte = (s: string) => s.replace(/\s/g, ' ')

describe('formatMontant', () => {
  it('affiche les montants à la française, en euros par défaut', () => {
    expect(texte(formatMontant(1250.5))).toBe('1 250,50 €')
    expect(texte(formatMontant(0))).toBe('0,00 €')
    expect(formatMontant(null)).toBe('—')
  })

  it('suit la devise : sans décimales pour l’ariary', () => {
    expect(texte(formatMontant(1250000, ARIARY))).toBe('1 250 000 Ar')
    expect(texte(formatMontant(1250.5, ARIARY))).toBe('1 251 Ar')
    expect(texte(formatMontant(19.9, DOLLAR))).toBe('19,90 $')
  })

  it('abrège les montants des graphiques', () => {
    expect(texte(formatMontantCompact(117000))).toBe('117 k €')
    expect(texte(formatMontantCompact(1250000, ARIARY))).toBe('1,3 M Ar')
  })
})

describe('montantEnSaisie', () => {
  it('prépare la valeur d’un champ selon les décimales de la devise', () => {
    expect(montantEnSaisie(1250.5)).toBe('1250,50')
    expect(montantEnSaisie(150000, ARIARY)).toBe('150000')
  })
})

describe('lireMontant', () => {
  it('accepte virgule, point, espaces et symbole de la devise', () => {
    expect(lireMontant('1 250,50')).toBe(1250.5)
    expect(lireMontant('1250.5')).toBe(1250.5)
    expect(lireMontant('300 €')).toBe(300)
    expect(lireMontant('1 000')).toBe(1000)
    expect(lireMontant('150 000 Ar', ARIARY)).toBe(150000)
  })

  it('refuse les montants invalides, nuls ou à plus de décimales que la devise', () => {
    expect(lireMontant('')).toBeNull()
    expect(lireMontant('0')).toBeNull()
    expect(lireMontant('-5')).toBeNull()
    expect(lireMontant('12,345')).toBeNull()
    expect(lireMontant('douze')).toBeNull()
    expect(lireMontant('1500,50', ARIARY)).toBeNull()
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
