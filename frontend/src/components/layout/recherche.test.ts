import { GraduationCap, Wallet } from 'lucide-react'
import { describe, expect, it } from 'vitest'
import type { NavGroup } from '@/config/navigation'
import { deplacer, filtrerPages, normaliser } from './recherche'

const GROUPES: NavGroup[] = [
  { label: 'Personnes', items: [{ label: 'Élèves', path: '/eleves', icon: GraduationCap, roles: ['admin'] }] },
  { label: 'Administration', items: [{ label: 'Paiements', path: '/paiements', icon: Wallet, roles: ['admin'] }] },
]

describe('normaliser', () => {
  it('ignore la casse et les accents', () => {
    expect(normaliser('  Élèves ÉTÉ ')).toBe('eleves ete')
  })
})

describe('filtrerPages', () => {
  it('propose tous les écrans sans saisie', () => {
    expect(filtrerPages(GROUPES, '').map((p) => p.path)).toEqual(['/eleves', '/paiements'])
  })

  it('cherche sans accent, dans le libellé et le groupe', () => {
    expect(filtrerPages(GROUPES, 'eleve').map((p) => p.path)).toEqual(['/eleves'])
    expect(filtrerPages(GROUPES, 'admin paie').map((p) => p.path)).toEqual(['/paiements'])
    expect(filtrerPages(GROUPES, 'bulletins')).toEqual([])
  })
})

describe('deplacer', () => {
  it('boucle en fin et en début de liste', () => {
    expect(deplacer(2, 1, 3)).toBe(0)
    expect(deplacer(0, -1, 3)).toBe(2)
    expect(deplacer(0, 1, 0)).toBe(0)
  })
})
