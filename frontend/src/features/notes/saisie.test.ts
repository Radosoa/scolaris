import { describe, expect, it } from 'vitest'
import { lignesModifiees, lireNote, statistiquesSaisie, versPayload, type LigneEdition } from './saisie'
import type { LigneGrille } from './types'

const eleve = (eleve_id: number, valeur: number | null, absent = false): LigneGrille => ({
  eleve_id,
  matricule: `2026-000${eleve_id}`,
  nom: 'Nom',
  prenom: 'Prénom',
  inscrit: true,
  valeur,
  absent,
})

describe('lireNote', () => {
  it('accepte la virgule et le point', () => {
    expect(lireNote('12,5', 20)).toEqual({ ok: true, valeur: 12.5 })
    expect(lireNote(' 7.25 ', 20)).toEqual({ ok: true, valeur: 7.25 })
  })

  it('traite une saisie vide comme une absence de note', () => {
    expect(lireNote('', 20)).toEqual({ ok: true, valeur: null })
  })

  it('refuse les notes au-dessus du barème, négatives ou mal formées', () => {
    expect(lireNote('21', 20)).toEqual({ ok: false, erreur: 'Maximum 20.' })
    expect(lireNote('10,5', 10)).toEqual({ ok: false, erreur: 'Maximum 10.' })
    expect(lireNote('-2', 20)).toMatchObject({ ok: false, erreur: 'Note négative.' })
    expect(lireNote('12,345', 20)).toMatchObject({ ok: false })
    expect(lireNote('abc', 20)).toMatchObject({ ok: false })
  })

  it('accepte exactement le barème et zéro', () => {
    expect(lireNote('20', 20)).toEqual({ ok: true, valeur: 20 })
    expect(lireNote('0', 20)).toEqual({ ok: true, valeur: 0 })
  })
})

describe('lignesModifiees', () => {
  const origine = [eleve(1, 12), eleve(2, null), eleve(3, null, true)]

  it('ignore une réécriture équivalente (12 → « 12,0 »)', () => {
    const lignes: LigneEdition[] = [
      { eleve_id: 1, texte: '12,0', absent: false },
      { eleve_id: 2, texte: '', absent: false },
      { eleve_id: 3, texte: '', absent: true },
    ]
    expect(lignesModifiees(lignes, origine, 20)).toEqual([])
  })

  it('détecte une nouvelle note, un changement d’absence et une note invalide', () => {
    const lignes: LigneEdition[] = [
      { eleve_id: 1, texte: '25', absent: false },
      { eleve_id: 2, texte: '8', absent: false },
      { eleve_id: 3, texte: '', absent: false },
    ]
    expect(lignesModifiees(lignes, origine, 20).map((l) => l.eleve_id)).toEqual([1, 2, 3])
  })
})

describe('versPayload', () => {
  it('envoie une valeur nulle pour les absents et les saisies vides', () => {
    expect(
      versPayload(
        [
          { eleve_id: 1, texte: '14,5', absent: false },
          { eleve_id: 2, texte: '18', absent: true },
          { eleve_id: 3, texte: '', absent: false },
        ],
        20,
      ),
    ).toEqual([
      { eleve_id: 1, valeur: 14.5, absent: false },
      { eleve_id: 2, valeur: null, absent: true },
      { eleve_id: 3, valeur: null, absent: false },
    ])
  })
})

describe('statistiquesSaisie', () => {
  it('exclut les absents et les notes invalides de la moyenne', () => {
    const stats = statistiquesSaisie(
      [
        { eleve_id: 1, texte: '10', absent: false },
        { eleve_id: 2, texte: '15', absent: false },
        { eleve_id: 3, texte: '', absent: true },
        { eleve_id: 4, texte: '99', absent: false },
        { eleve_id: 5, texte: '', absent: false },
      ],
      20,
    )
    expect(stats).toEqual({ saisies: 3, absents: 1, moyenne: 12.5, min: 10, max: 15 })
  })

  it('renvoie des statistiques vides sans note', () => {
    expect(statistiquesSaisie([{ eleve_id: 1, texte: '', absent: false }], 20)).toEqual({
      saisies: 0,
      absents: 0,
      moyenne: null,
      min: null,
      max: null,
    })
  })
})
