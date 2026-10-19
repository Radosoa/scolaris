import { CalendarCheck, CheckCircle2, FileText, GraduationCap, Receipt, Sparkles } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

const ATOUTS = [
  { icone: FileText, texte: 'Bulletins calculés automatiquement, prêts à imprimer' },
  { icone: CalendarCheck, texte: 'Appel, absences et emploi du temps sans conflit' },
  { icone: Receipt, texte: 'Frais de scolarité, échéancier et reçus en un clic' },
]

/**
 * Panneau visuel de la page de connexion : dégradé de la marque et aperçus « flottants »
 * de l'application. Purement décoratif (aria-hidden), masqué sur mobile.
 */
export function Vitrine() {
  return (
    <div
      aria-hidden
      className="relative hidden overflow-hidden bg-[linear-gradient(145deg,oklch(0.36_0.2_277)_0%,oklch(0.46_0.24_285)_45%,oklch(0.6_0.22_310)_100%)] p-10 text-white lg:flex lg:flex-col"
    >
      {/* Halos et trame décoratifs */}
      <div className="absolute -top-24 -left-24 size-80 rounded-full bg-fuchsia-400/30 blur-3xl" />
      <div className="absolute -right-20 bottom-0 size-96 rounded-full bg-indigo-300/25 blur-3xl" />
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgb(255_255_255/0.12)_1px,transparent_0)] [background-size:22px_22px]" />

      <div className="relative flex items-center gap-2.5">
        <div className="flex size-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
          <GraduationCap className="size-5" />
        </div>
        <span className="text-lg font-semibold tracking-tight">Scolaris</span>
      </div>

      {/* Aperçus de l'application */}
      <div className="relative my-10 flex-1">
        <CarteFlottante className="top-2 left-0 w-64 -rotate-3 motion-safe:animate-[flotter_7s_ease-in-out_infinite]">
          <p className="text-[11px] font-medium tracking-wide text-white/70 uppercase">Bulletin · Trimestre 1</p>
          <p className="mt-1 text-sm font-medium">Lucas Petit · 6e A</p>
          <div className="mt-2 flex items-end justify-between">
            <p className="text-3xl font-semibold tabular-nums">
              15,40<span className="ml-1 text-sm font-normal text-white/70">/ 20</span>
            </p>
            <span className="rounded-full bg-emerald-400/20 px-2 py-0.5 text-[11px] font-medium text-emerald-100 ring-1 ring-emerald-300/40">
              2e sur 25
            </span>
          </div>
          <div className="mt-3 flex h-10 items-end gap-1.5">
            {[55, 72, 48, 80, 66, 90, 74].map((h, i) => (
              <span key={i} className="w-full rounded-t-sm bg-white/40" style={{ height: `${h}%` }} />
            ))}
          </div>
        </CarteFlottante>

        <CarteFlottante className="top-36 right-0 w-56 rotate-2 motion-safe:animate-[flotter_8s_ease-in-out_1s_infinite]">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="size-4 text-emerald-300" />
            <p className="text-sm font-medium">Appel validé</p>
          </div>
          <p className="mt-1 text-xs text-white/70">6e A · Mathématiques · 08:00</p>
          <div className="mt-3 flex -space-x-2">
            {['AL', 'BD', 'CG', 'DM', 'EP'].map((initiales) => (
              <span key={initiales} className="flex size-8 items-center justify-center rounded-full bg-indigo-400/60 text-[10px] font-medium ring-2 ring-indigo-700/70">
                {initiales}
              </span>
            ))}
            <span className="flex size-8 items-center justify-center rounded-full bg-white text-[10px] font-semibold text-indigo-700 ring-2 ring-indigo-700/70">
              +19
            </span>
          </div>
          <p className="mt-2 text-xs text-white/80">24 présents · 1 retard</p>
        </CarteFlottante>

        <CarteFlottante className="bottom-0 left-10 w-60 -rotate-1 motion-safe:animate-[flotter_9s_ease-in-out_0.5s_infinite]">
          <div className="flex items-center justify-between">
            <p className="text-xs text-white/70">Reçu R2026-00042</p>
            <Sparkles className="size-3.5 text-amber-200" />
          </div>
          <p className="mt-1 text-xl font-semibold tabular-nums">150 000 Ar</p>
          <p className="text-xs text-white/70">Cent cinquante mille ariary</p>
        </CarteFlottante>
      </div>

      <div className="relative space-y-4">
        <h2 className="text-2xl leading-tight font-semibold text-balance">Toute la vie de l’établissement, au même endroit.</h2>
        <ul className="space-y-2.5">
          {ATOUTS.map(({ icone: Icone, texte }) => (
            <li key={texte} className="flex items-center gap-3 text-sm text-white/85">
              <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20">
                <Icone className="size-3.5" />
              </span>
              {texte}
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

function CarteFlottante({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <div className={cn('absolute rounded-2xl bg-white/12 p-4 shadow-2xl shadow-indigo-950/30 ring-1 ring-white/25 backdrop-blur-md', className)}>
      {children}
    </div>
  )
}
