/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** URL de l'API Laravel, sans le suffixe /api (ex. https://scolaris-api.onrender.com) */
  readonly VITE_API_URL: string
  /** "true" pour afficher les boutons de comptes de démonstration sur la page de connexion */
  readonly VITE_SHOW_DEMO_ACCOUNTS?: string
  readonly VITE_DOMAINE_ECOLES?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
