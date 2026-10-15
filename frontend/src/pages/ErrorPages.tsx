import { Link } from 'react-router'
import { Button } from '@/components/ui/button'

function ErrorPage({ code, title, message }: { code: string; title: string; message: string }) {
  return (
    <div className="flex min-h-[60svh] flex-col items-center justify-center gap-3 text-center">
      <p className="text-sm font-semibold text-primary">{code}</p>
      <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
      <p className="max-w-md text-sm text-muted-foreground">{message}</p>
      <Button asChild className="mt-2">
        <Link to="/tableau-de-bord">Retour au tableau de bord</Link>
      </Button>
    </div>
  )
}

export function ForbiddenPage() {
  return (
    <ErrorPage
      code="403"
      title="Accès refusé"
      message="Vous n'avez pas les droits nécessaires pour consulter cette page."
    />
  )
}

export function NotFoundPage() {
  return (
    <ErrorPage code="404" title="Page introuvable" message="La page demandée n'existe pas ou a été déplacée." />
  )
}
