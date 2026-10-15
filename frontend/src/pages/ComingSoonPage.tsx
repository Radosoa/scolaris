import { Construction } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader'

/** Emplacement des écrans livrés dans les phases suivantes. */
export function ComingSoonPage({ title }: { title: string }) {
  return (
    <>
      <PageHeader title={title} />
      <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed bg-background py-16 text-center">
        <Construction className="size-8 text-muted-foreground" />
        <p className="text-sm text-muted-foreground">Cet écran sera disponible prochainement.</p>
      </div>
    </>
  )
}
