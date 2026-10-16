import { zodResolver } from '@hookform/resolvers/zod'
import {
  AlertCircle,
  ArrowRight,
  Building2,
  ClipboardList,
  Eye,
  EyeOff,
  GraduationCap,
  Loader2,
  Lock,
  Mail,
  Presentation,
  Users,
  type LucideIcon,
} from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { applyValidationErrors, getErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'
import { useAuth } from './auth-context'
import { ChoixTheme } from '@/components/layout/ChoixTheme'
import { Vitrine } from './components/Vitrine'
import { loginSchema, type LoginValues } from './schemas'

const AFFICHER_DEMO = import.meta.env.VITE_SHOW_DEMO_ACCOUNTS === 'true'
const ANNEE = new Date().getFullYear()

const COMPTES_DEMO: { libelle: string; detail: string; email: string; icone: LucideIcon }[] = [
  { libelle: 'Direction', detail: 'Pilotage, bulletins', email: 'admin@scolaris.test', icone: Building2 },
  { libelle: 'Secrétariat', detail: 'Dossiers, paiements', email: 'secretariat@scolaris.test', icone: ClipboardList },
  { libelle: 'Professeur', detail: 'Notes, appel', email: 'professeur@scolaris.test', icone: Presentation },
  { libelle: 'Élève', detail: 'Notes, emploi du temps', email: 'eleve@scolaris.test', icone: GraduationCap },
  { libelle: 'Parent', detail: 'Suivi des enfants', email: 'parent@scolaris.test', icone: Users },
]

export function LoginPage() {
  const { login } = useAuth()
  const [erreur, setErreur] = useState<string | null>(null)
  const [motDePasseVisible, setMotDePasseVisible] = useState(false)

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

  /** Compte de démonstration : remplit le formulaire et se connecte directement. */
  const essayer = (email: string) => {
    form.setValue('email', email, { shouldValidate: true })
    form.setValue('password', 'password', { shouldValidate: true })
    void onSubmit()
  }

  return (
    <div className="relative flex min-h-svh items-center justify-center overflow-hidden bg-slate-50 p-4 sm:p-8 dark:bg-background">
      {/* Fond décoratif : halos de couleur et trame légère */}
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute -top-40 left-1/4 size-[32rem] rounded-full bg-indigo-300/40 blur-3xl dark:bg-indigo-900/40" />
        <div className="absolute -right-32 -bottom-40 size-[30rem] rounded-full bg-fuchsia-300/30 blur-3xl dark:bg-fuchsia-900/30" />
        <div className="absolute inset-0 bg-[linear-gradient(to_right,rgb(99_102_241/0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgb(99_102_241/0.06)_1px,transparent_1px)] [background-size:48px_48px] [mask-image:radial-gradient(ellipse_at_center,black_40%,transparent_75%)]" />
      </div>

      <ChoixTheme className="absolute top-3 right-3 z-10 sm:top-5 sm:right-5" />

      <main className="relative grid w-full max-w-5xl overflow-hidden rounded-3xl bg-background shadow-2xl ring-1 shadow-indigo-950/10 ring-black/5 lg:min-h-[40rem] lg:grid-cols-[1.05fr_1fr] dark:ring-white/10">
        <Vitrine />

        <section className="flex flex-col justify-center px-6 py-10 sm:px-12 lg:px-14">
          <div className="mx-auto w-full max-w-sm">
            <div className="mb-8 flex flex-col items-center text-center">
              <div className="mb-4 flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-500 text-white shadow-lg shadow-indigo-500/30">
                <GraduationCap className="size-6" />
              </div>
              <h1 className="text-2xl font-semibold tracking-tight">Bienvenue sur Scolaris</h1>
              <p className="mt-1.5 text-sm text-muted-foreground">Connectez-vous avec les identifiants fournis par l’établissement.</p>
            </div>

            <form onSubmit={onSubmit} noValidate className="space-y-4">
              {erreur && (
                <Alert variant="destructive">
                  <AlertCircle />
                  <AlertDescription>{erreur}</AlertDescription>
                </Alert>
              )}

              <div className="space-y-1.5">
                <Label htmlFor="email" className="text-xs text-muted-foreground">
                  Adresse e-mail
                </Label>
                <div className="relative">
                  <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    placeholder="prenom.nom@exemple.fr"
                    className="h-11 pl-9"
                    aria-invalid={!!errors.email}
                    aria-describedby={errors.email ? 'email-erreur' : undefined}
                    {...form.register('email')}
                  />
                </div>
                {errors.email && (
                  <p id="email-erreur" className="text-sm text-destructive">
                    {errors.email.message}
                  </p>
                )}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="password" className="text-xs text-muted-foreground">
                  Mot de passe
                </Label>
                <div className="relative">
                  <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="password"
                    type={motDePasseVisible ? 'text' : 'password'}
                    autoComplete="current-password"
                    placeholder="Votre mot de passe"
                    className="h-11 pr-10 pl-9"
                    aria-invalid={!!errors.password}
                    aria-describedby={errors.password ? 'password-erreur' : undefined}
                    {...form.register('password')}
                  />
                  <button
                    type="button"
                    onClick={() => setMotDePasseVisible((v) => !v)}
                    aria-label={motDePasseVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                    aria-pressed={motDePasseVisible}
                    className="absolute top-1/2 right-1.5 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                  >
                    {motDePasseVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                  </button>
                </div>
                {errors.password && (
                  <p id="password-erreur" className="text-sm text-destructive">
                    {errors.password.message}
                  </p>
                )}
              </div>

              <Button
                type="submit"
                className="h-11 w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 text-sm shadow-lg shadow-indigo-500/25 hover:from-indigo-700 hover:to-violet-700"
                disabled={isSubmitting}
              >
                {isSubmitting ? <Loader2 className="animate-spin" /> : null}
                Se connecter
                {!isSubmitting && <ArrowRight />}
              </Button>

              <p className="text-center text-xs text-muted-foreground">
                Mot de passe oublié ? Contactez le secrétariat de l’établissement.
              </p>
            </form>

            {AFFICHER_DEMO && (
              <div className="mt-8">
                <div className="mb-3 flex items-center gap-3 text-xs text-muted-foreground">
                  <span className="h-px flex-1 bg-border" />
                  Essayer avec un compte de démonstration
                  <span className="h-px flex-1 bg-border" />
                </div>
                <div className="flex flex-wrap justify-center gap-2">
                  {COMPTES_DEMO.map(({ libelle, detail, email, icone: Icone }, i) => (
                    <button
                      key={email}
                      type="button"
                      onClick={() => essayer(email)}
                      disabled={isSubmitting}
                      title={email}
                      className={cn(
                        'group flex flex-col items-start gap-1 rounded-xl border bg-background p-2.5 text-left transition-all hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md dark:hover:border-indigo-500/60 hover:shadow-indigo-500/10 disabled:opacity-60',
                        'w-[calc(50%-0.25rem)] sm:w-[calc(33.333%-0.34rem)]',
                        // Le dernier compte occupe toute la ligne sur mobile (5 comptes, 2 colonnes)
                        i === COMPTES_DEMO.length - 1 && 'w-full sm:w-[calc(33.333%-0.34rem)]',
                      )}
                    >
                      <Icone className="size-4 text-indigo-600 transition-transform group-hover:scale-110 dark:text-indigo-300" />
                      <span className="text-sm font-medium">{libelle}</span>
                      <span className="text-[11px] leading-tight text-muted-foreground">{detail}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            <p className="mt-10 text-center text-xs text-muted-foreground">© {ANNEE} Scolaris · Gestion scolaire</p>
          </div>
        </section>
      </main>
    </div>
  )
}
