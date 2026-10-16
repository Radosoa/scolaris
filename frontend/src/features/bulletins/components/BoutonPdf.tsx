import { FileDown, Loader2 } from 'lucide-react'
import { useState, type ComponentProps } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { getErrorMessage } from '@/lib/api'

interface Props extends Omit<ComponentProps<typeof Button>, 'onClick'> {
  telecharger: () => Promise<void>
  libelle?: string
}

/** Téléchargement d'un PDF protégé par le token, avec indicateur et message d'erreur. */
export function BoutonPdf({ telecharger, libelle = 'PDF', variant = 'outline', size = 'sm', ...props }: Props) {
  const [enCours, setEnCours] = useState(false)

  const lancer = async () => {
    setEnCours(true)
    try {
      await telecharger()
    } catch (error) {
      toast.error(getErrorMessage(error))
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Button variant={variant} size={size} onClick={lancer} disabled={enCours || props.disabled} {...props}>
      {enCours ? <Loader2 className="animate-spin" /> : <FileDown />}
      {libelle}
    </Button>
  )
}
