import { zodResolver } from '@hookform/resolvers/zod'
import { AlertCircle, GraduationCap, Loader2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { applyValidationErrors, getErrorMessage } from '@/lib/api'
import { useAuth } from './auth-context'
import { loginSchema, type LoginValues } from './schemas'

const AFFICHER_DEMO = import.meta.env.VITE_SHOW_DEMO_ACCOUNTS === 'true'

const COMPTES_DEMO = [
  { libelle: 'Direction', email: 'admin@scolaris.test' },
  { libelle: 'Secrétariat', email: 'secretariat@scolaris.test' },
  { libelle: 'Professeur', email: 'professeur@scolaris.test' },
  { libelle: 'Élève', email: 'eleve@scolaris.test' },
  { libelle: 'Parent', email: 'parent@scolaris.test' },
]

export function LoginPage() {
  const { login } = useAuth()
  const [erreur, setErreur] = useState<string | null>(null)

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })
  const { errors, isSubmitting } = form.formState

  const onSubmit = form.handleSubmit(async (values) => {
    setErreur(null)
    try {
      // La redirection est faite par GuestRoute dès que l'utilisateur est authentifié
      await login(values)
    } catch (error) {
      if (!applyValidationErrors(error, form.setError)) {
        setErreur(getErrorMessage(error))
      }
    }
  })

  const remplirDemo = (email: string) => {
    form.setValue('email', email, { shouldValidate: true })
    form.setValue('password', 'password', { shouldValidate: true })
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-muted/40 p-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="flex flex-col items-center gap-2 text-center">
          <div className="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <GraduationCap className="size-6" />
          </div>
          <h1 className="text-2xl font-semibold tracking-tight">Scolaris</h1>
          <p className="text-sm text-muted-foreground">Gestion scolaire</p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Connexion</CardTitle>
            <CardDescription>Accédez à votre espace avec vos identifiants.</CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={onSubmit} noValidate className="space-y-4">
              {erreur && (
                <Alert variant="destructive">
                  <AlertCircle />
                  <AlertDescription>{erreur}</AlertDescription>
                </Alert>
              )}

              <div className="space-y-2">
                <Label htmlFor="email">Adresse e-mail</Label>
                <Input
                  id="email"
                  type="email"
                  autoComplete="username"
                  autoFocus
                  aria-invalid={!!errors.email}
                  {...form.register('email')}
                />
                {errors.email && <p className="text-sm text-destructive">{errors.email.message}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Mot de passe</Label>
                <Input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  aria-invalid={!!errors.password}
                  {...form.register('password')}
                />
                {errors.password && <p className="text-sm text-destructive">{errors.password.message}</p>}
              </div>

              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting && <Loader2 className="animate-spin" />}
                Se connecter
              </Button>
            </form>
          </CardContent>
        </Card>

        {AFFICHER_DEMO && (
        <div className="space-y-2 text-center">
          <p className="text-xs text-muted-foreground">Comptes de démonstration</p>
          <div className="flex flex-wrap justify-center gap-2">
            {COMPTES_DEMO.map((compte) => (
              <Button
                key={compte.email}
                type="button"
                variant="outline"
                size="sm"
                onClick={() => remplirDemo(compte.email)}
              >
                {compte.libelle}
              </Button>
            ))}
          </div>
        </div>
        )}
      </div>
    </div>
  )
}
