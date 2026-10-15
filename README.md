# Scolaris — ERP de gestion scolaire

Application web de gestion d'établissement scolaire : élèves, enseignants, matières, notes,
**bulletins calculés automatiquement**, absences, emploi du temps et paiements.

> Projet portfolio en cours de développement. Ce README sera complété au fil des phases
> (captures d'écran, schéma de la base, architecture détaillée).

## Stack

| Couche | Technologies |
|---|---|
| API | Laravel 13 (PHP 8.4), Sanctum (tokens Bearer), spatie/laravel-permission, dompdf, Laravel Excel |
| Base de données | MySQL 8 |
| Frontend | React 19, Vite, TypeScript, React Router, TanStack Query, React Hook Form + Zod, Tailwind CSS 4, shadcn/ui |
| Outillage | Docker Compose (dev), PHPUnit, oxlint |

## Structure du dépôt

```
scolaris/
├── backend/            API REST Laravel
├── frontend/           SPA React
├── docker/             image PHP de dev et initialisation MySQL
└── docker-compose.yml  environnement de développement
```

## Installation locale

Prérequis : **Docker Desktop** et **Node.js ≥ 20**. PHP et MySQL tournent dans des conteneurs :
aucune installation locale de PHP n'est nécessaire.

```bash
# 1. Backend : configuration et démarrage
cp backend/.env.example backend/.env
docker compose run --rm api composer install
docker compose run --rm api php artisan key:generate
docker compose up -d
docker compose exec api php artisan migrate --seed

# 2. Frontend
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

- API : http://localhost:8001
- Application : http://localhost:5173
- MySQL depuis l'hôte : `127.0.0.1:3307` (utilisateur `scolaris` / `secret`)

### Tests

```bash
docker compose exec api php artisan test   # backend (base MySQL scolaris_test)
cd frontend && npm run build && npm run lint
```

## Comptes de démonstration

Mot de passe commun : `password`

| Rôle | E-mail |
|---|---|
| Direction | admin@scolaris.test |
| Secrétariat | secretariat@scolaris.test |
| Professeur | professeur@scolaris.test |
| Élève | eleve@scolaris.test |
| Parent | parent@scolaris.test |

## API — authentification

| Méthode | Route | Description |
|---|---|---|
| `POST` | `/api/auth/login` | Renvoie un token Bearer et l'utilisateur (5 tentatives / minute) |
| `POST` | `/api/auth/logout` | Révoque le token courant |
| `GET` | `/api/me` | Utilisateur connecté, rôles et permissions |

## Déploiement

- **Frontend** (Vercel ou Netlify) : définir `VITE_API_URL`. Les fichiers `vercel.json` et
  `netlify.toml` gèrent la réécriture SPA.
- **Backend** (Render, AlwaysData…) : `backend/Dockerfile` construit une image Apache + PHP 8.4.
  Variables minimales : `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `DB_*`. Le port est lu dans `$PORT`
  et les migrations s'exécutent au démarrage (`RUN_MIGRATIONS=false` pour les désactiver).
  Sans worker permanent, utiliser `QUEUE_CONNECTION=sync`.
