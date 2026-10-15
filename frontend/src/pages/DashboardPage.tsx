import { PageHeader } from '@/components/PageHeader'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { navigationPour } from '@/config/navigation'
import { useAuth } from '@/features/auth/auth-context'
import { Link } from 'react-router'

const INTRODUCTIONS = {
  admin: 'Pilotez l’établissement : structure, effectifs, résultats et finances.',
  secretariat: 'Gérez les inscriptions, les dossiers élèves et les paiements.',
  professeur: 'Saisissez les notes et les absences de vos classes.',
  eleve: 'Consultez vos notes, votre emploi du temps et vos bulletins.',
  parent: 'Suivez la scolarité de vos enfants.',
} as const

/** Tableau de bord provisoire : les indicateurs arrivent en phase 8. */
export function DashboardPage() {
  const { user } = useAuth()
  if (!user) return null

  const role = user.roles[0]
  const raccourcis = navigationPour(user.roles)
    .flatMap((groupe) => groupe.items)
    .filter((item) => item.path !== '/tableau-de-bord')

  return (
    <>
      <PageHeader title={`Bonjour, ${user.name.split(' ')[0]}`} description={INTRODUCTIONS[role]} />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {raccourcis.map((item) => (
          <Link key={item.path} to={item.path} className="rounded-xl focus-visible:outline-2 focus-visible:outline-ring">
            <Card className="h-full transition-colors hover:bg-accent/40">
              <CardHeader>
                <item.icon className="mb-2 size-5 text-primary" />
                <CardTitle className="text-base">{item.label}</CardTitle>
                <CardDescription>Accéder à la rubrique</CardDescription>
              </CardHeader>
            </Card>
          </Link>
        ))}
      </div>
    </>
  )
}
