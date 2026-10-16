import { describe, expect, it } from 'vitest'
import { pagesAffichees } from './pages'

describe('pagesAffichees', () => {
  it('affiche toutes les pages quand il y en a peu', () => {
    expect(pagesAffichees(1, 1)).toEqual([1])
    expect(pagesAffichees(2, 4)).toEqual([1, 2, 3, 4])
  })

  it('remplace les trous par « … » autour de la page courante', () => {
    expect(pagesAffichees(6, 12)).toEqual([1, '…', 5, 6, 7, '…', 12])
    expect(pagesAffichees(1, 10)).toEqual([1, 2, '…', 10])
    expect(pagesAffichees(10, 10)).toEqual([1, '…', 9, 10])
  })

  it('n’affiche pas « … » pour une seule page manquante', () => {
    expect(pagesAffichees(4, 10)).toEqual([1, 2, 3, 4, 5, '…', 10])
  })
})
