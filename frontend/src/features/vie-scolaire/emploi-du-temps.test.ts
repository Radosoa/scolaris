import { describe, expect, it } from 'vitest'
import {
  compterAppel,
  derniereOccurrence,
  formatHeure,
  heureDecimale,
  jourIso,
  plageDe,
  position,
  teinteMatiere,
  totalHeures,
} from './emploi-du-temps'

describe('heures', () => {
  it('convertit « HH:MM » en heures décimales et inversement', () => {
    expect(heureDecimale('08:30')).toBe(8.5)
    expect(heureDecimale('14:00')).toBe(14)
    expect(formatHeure(8.5)).toBe('08:30')
    expect(formatHeure(16)).toBe('16:00')
  })
})

describe('plageDe', () => {
  it('affiche au minimum 8 h – 17 h du lundi au vendredi', () => {
    expect(plageDe([])).toEqual({ debut: 8, fin: 17, jours: [1, 2, 3, 4, 5] })
  })

  it('s’élargit aux créneaux matinaux, tardifs et au samedi', () => {
    expect(plageDe([{ jour: 6, heure_debut: '07:30', heure_fin: '08:30' }, { jour: 2, heure_debut: '17:00', heure_fin: '18:15' }])).toEqual({
      debut: 7,
      fin: 19,
      jours: [1, 2, 3, 4, 5, 6],
    })
  })
})

describe('position', () => {
  it('place un créneau en pourcentage de la journée affichée', () => {
    expect(position({ heure_debut: '10:00', heure_fin: '12:00' }, { debut: 8, fin: 18, jours: [1] })).toEqual({ top: 20, height: 20 })
  })
})

describe('totalHeures', () => {
  it('additionne les durées', () => {
    expect(totalHeures([{ heure_debut: '08:00', heure_fin: '10:00' }, { heure_debut: '14:00', heure_fin: '14:30' }])).toBe(2.5)
  })
})

describe('teinteMatiere', () => {
  it('donne toujours la même teinte à une matière', () => {
    expect(teinteMatiere('MATH', 8)).toBe(teinteMatiere('MATH', 8))
    expect(teinteMatiere('MATH', 8)).toBeLessThan(8)
  })
})

describe('jourIso', () => {
  it('donne le jour de la semaine sans décalage de fuseau', () => {
    expect(jourIso('2026-10-12')).toBe(1) // lundi
    expect(jourIso('2026-10-16')).toBe(5) // vendredi
    expect(jourIso('2026-10-18')).toBe(7) // dimanche
  })
})

describe('derniereOccurrence', () => {
  it('remonte au dernier jour de la semaine demandé, aujourd’hui inclus', () => {
    expect(derniereOccurrence(5, '2026-10-16')).toBe('2026-10-16') // vendredi → aujourd'hui
    expect(derniereOccurrence(1, '2026-10-16')).toBe('2026-10-12') // lundi précédent
    expect(derniereOccurrence(6, '2026-10-16')).toBe('2026-10-10') // samedi de la semaine passée
  })
})

describe('compterAppel', () => {
  it('compte présents, absents et retards', () => {
    expect(compterAppel([{ statut: 'present' }, { statut: 'absent' }, { statut: 'present' }, { statut: 'retard' }])).toEqual({
      present: 2,
      absent: 1,
      retard: 1,
    })
  })
})
