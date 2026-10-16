import { describe, expect, it } from 'vitest'
import { aujourdhuiIso, formatDate, formatNombre } from './format'

describe('formatDate', () => {
  it('convertit une date ISO sans décalage de fuseau', () => {
    expect(formatDate('2026-09-01')).toBe('01/09/2026')
    expect(formatDate('2026-09-01T23:30:00Z')).toBe('01/09/2026')
    expect(formatDate(null)).toBe('—')
  })
})

describe('formatNombre', () => {
  it('affiche au plus deux décimales', () => {
    expect(formatNombre(1.5)).toBe('1,5')
    expect(formatNombre(undefined)).toBe('—')
  })
})

describe('aujourdhuiIso', () => {
  it('utilise la date locale, pas l’UTC', () => {
    expect(aujourdhuiIso(new Date(2026, 0, 5, 23, 30))).toBe('2026-01-05')
  })
})
