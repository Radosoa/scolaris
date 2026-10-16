import {
  BookOpen,
  CalendarDays,
  CalendarRange,
  ClipboardCheck,
  ClipboardList,
  FileText,
  GraduationCap,
  LayoutDashboard,
  Megaphone,
  School,
  UserCheck,
  UserRoundPlus,
  UserRound,
  Users,
  Wallet,
  type LucideIcon,
} from 'lucide-react'
import type { Role } from '@/types/auth'

export interface NavItem {
  label: string
  path: string
  icon: LucideIcon
  roles: Role[]
}

export interface NavGroup {
  label: string | null
  items: NavItem[]
}

const TOUS: Role[] = ['admin', 'secretariat', 'professeur', 'eleve', 'parent']

/**
 * Source unique de la navigation : la sidebar et les routes protégées
 * sont générées à partir de cette configuration.
 */
export const NAVIGATION: NavGroup[] = [
  {
    label: null,
    items: [{ label: 'Tableau de bord', path: '/tableau-de-bord', icon: LayoutDashboard, roles: TOUS }],
  },
  {
    label: 'Structure',
    items: [
      { label: 'Années & périodes', path: '/structure/annees', icon: CalendarRange, roles: ['admin'] },
      { label: 'Niveaux & classes', path: '/structure/classes', icon: School, roles: ['admin'] },
      { label: 'Matières & coefficients', path: '/structure/matieres', icon: BookOpen, roles: ['admin'] },
    ],
  },
  {
    label: 'Personnes',
    items: [
      // Le professeur ne voit que les élèves de ses classes (filtré par l'API)
      { label: 'Élèves', path: '/eleves', icon: GraduationCap, roles: ['admin', 'secretariat', 'professeur'] },
      { label: 'Inscriptions', path: '/inscriptions', icon: UserRoundPlus, roles: ['admin', 'secretariat'] },
      { label: 'Parents', path: '/parents', icon: Users, roles: ['admin', 'secretariat'] },
      { label: 'Enseignants', path: '/enseignants', icon: UserRound, roles: ['admin', 'secretariat'] },
      { label: 'Mes enfants', path: '/mes-enfants', icon: Users, roles: ['parent'] },
    ],
  },
  {
    label: 'Pédagogie',
    items: [
      { label: 'Affectations', path: '/affectations', icon: ClipboardList, roles: ['admin'] },
      { label: 'Notes', path: '/notes', icon: ClipboardCheck, roles: ['admin', 'professeur', 'eleve'] },
      { label: 'Bulletins', path: '/bulletins', icon: FileText, roles: ['admin', 'professeur', 'eleve', 'parent'] },
    ],
  },
  {
    label: 'Vie scolaire',
    items: [
      { label: 'Appel', path: '/appel', icon: UserCheck, roles: ['professeur'] },
      { label: 'Absences', path: '/absences', icon: UserCheck, roles: ['admin', 'secretariat', 'parent'] },
      { label: 'Emploi du temps', path: '/emploi-du-temps', icon: CalendarDays, roles: ['admin', 'professeur', 'eleve'] },
    ],
  },
  {
    label: 'Administration',
    items: [
      { label: 'Paiements', path: '/paiements', icon: Wallet, roles: ['admin', 'secretariat', 'parent'] },
      { label: 'Annonces', path: '/annonces', icon: Megaphone, roles: TOUS },
    ],
  },
]

/** Navigation filtrée pour les rôles de l'utilisateur (groupes vides retirés). */
export function navigationPour(roles: Role[]): NavGroup[] {
  return NAVIGATION.map((groupe) => ({
    ...groupe,
    items: groupe.items.filter((item) => item.roles.some((role) => roles.includes(role))),
  })).filter((groupe) => groupe.items.length > 0)
}
