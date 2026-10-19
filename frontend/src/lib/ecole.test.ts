import { describe, expect, it } from 'vitest'
import { codeDepuisHote } from './ecole'

describe('codeDepuisHote', () => {
  it('lit le code de l’école dans le sous-domaine', () => {
    expect(codeDepuisHote('lycee-andohalo.scolaris.mg')).toBe('lycee-andohalo')
    expect(codeDepuisHote('demo.localhost')).toBe('demo')
  })

  it('ignore les adresses sans sous-domaine d’école', () => {
    expect(codeDepuisHote('localhost')).toBeNull()
    expect(codeDepuisHote('scolaris.mg')).toBeNull()
    expect(codeDepuisHote('www.scolaris.mg')).toBeNull()
    expect(codeDepuisHote('127.0.0.1')).toBeNull()
  })
})
