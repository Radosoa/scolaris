import { describe, expect, it } from 'vitest'
import { ilYA } from './temps'

const maintenant = new Date('2026-10-19T12:00:00Z').getTime()

describe('ilYA', () => {
  it('exprime l’ancienneté en français', () => {
    expect(ilYA('2026-10-19T11:59:30Z', maintenant)).toBe('à l’instant')
    expect(ilYA('2026-10-19T11:55:00Z', maintenant)).toBe('il y a 5 minutes')
    expect(ilYA('2026-10-19T09:00:00Z', maintenant)).toBe('il y a 3 heures')
    expect(ilYA('2026-10-18T12:00:00Z', maintenant)).toBe('hier')
    expect(ilYA('2026-10-16T12:00:00Z', maintenant)).toBe('il y a 3 jours')
  })

  it('affiche la date au-delà d’une semaine', () => {
    expect(ilYA('2026-10-01T12:00:00Z', maintenant)).toBe('1 oct.')
  })
})
