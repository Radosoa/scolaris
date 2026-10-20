import { describe, expect, it } from 'vitest'
import { codeDepuisHote } from './ecole'

describe('codeDepuisHote', () => {
  it('lit le code de l’école dans un sous-domaine du domaine des écoles', () => {
    expect(codeDepuisHote('lycee-andohalo.scolaris.mg', 'scolaris.mg')).toBe('lycee-andohalo')
    expect(codeDepuisHote('demo.localhost')).toBe('demo')
  })

  it('ignore les adresses d’hébergeur et celles sans sous-domaine d’école', () => {
    // Sans domaine configuré, « scolaris-erp » n'est pas une école
    expect(codeDepuisHote('scolaris-erp.vercel.app')).toBeNull()
    expect(codeDepuisHote('scolaris-erp.vercel.app', 'scolaris.mg')).toBeNull()
    expect(codeDepuisHote('localhost')).toBeNull()
    expect(codeDepuisHote('scolaris.mg', 'scolaris.mg')).toBeNull()
    expect(codeDepuisHote('www.scolaris.mg', 'scolaris.mg')).toBeNull()
    expect(codeDepuisHote('a.b.scolaris.mg', 'scolaris.mg')).toBeNull()
    expect(codeDepuisHote('127.0.0.1')).toBeNull()
  })
})
