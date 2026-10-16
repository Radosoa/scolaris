import { createResource } from '@/lib/resource'
import type { Annonce, AnnoncePayload } from './types'

export const annonces = createResource<Annonce, AnnoncePayload>('annonces', '/annonces')
