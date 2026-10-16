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
- Le service `worker` traite la file d'attente (génération des PDF de bulletins par classe)

### Tests

```bash
docker compose exec api php artisan test   # backend : unitaires + fonctionnels (base MySQL scolaris_test)
cd frontend && npm run test && npm run lint && npm run build
```

## Vie scolaire

- **Emploi du temps** construit par la direction, par classe ou par professeur. Un créneau est
  refusé (422, messages détaillés) s'il chevauche un cours du même professeur, de la même classe
  ou dans la même salle sur l'année scolaire. Deux cours qui se suivent (8 h – 9 h, 9 h – 10 h)
  ne sont pas en conflit.
- **Appel numérique** par le professeur, sur ses seuls créneaux : élèves présents par défaut,
  absents sur la durée du cours, retards en minutes. L'appel est tracé même si tout le monde est
  présent (table `appels`). Une justification déjà saisie est conservée si le statut ne change pas.
- **Justification** par le secrétariat, pour une absence ou toute la journée de l'élève.
- Les heures d'absence (justifiées / non justifiées) et les retards alimentent les bulletins.

## Bulletins automatiques

Le calcul est isolé dans `MoyenneCalculator` (classe pure, sans base de données, couverte par
des tests unitaires) et orchestré par `BulletinService`.

- Chaque note est ramenée sur 20 ; moyenne par matière = 40 % devoirs et interrogations
  + 60 % composition (pondérations dans `config/bulletin.php`, surchargeables par `.env`) ;
  si une catégorie est vide, l'autre compte seule.
- Un élève absent n'est compté ni comme zéro ni dans le diviseur ; une matière sans note
  est exclue de la moyenne générale (Σ moyenne × coefficient du niveau / Σ coefficients).
- Rangs calculés sur les valeurs non arrondies, ex æquo partagés (1, 2, 2, 4) ;
  moyennes stockées avec 4 décimales, affichées avec 2.
- Mentions selon des seuils configurables ; heures d'absence et retards de la période.
- Calcul possible uniquement sur une période clôturée ; une fois validés, les bulletins sont
  figés (ni recalcul, ni appréciation, ni réouverture de la période).
- PDF individuels (à la volée, mention « Projet » pour un brouillon) et génération en lot par
  classe dans un job de queue, avec un PDF regroupé pour l'impression.

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
  Sans worker permanent, utiliser `QUEUE_CONNECTION=sync` : la génération des PDF d'une classe
  (~10 s pour 25 élèves) s'exécute alors dans la requête. Les PDF sont stockés sur le disque
  local du conteneur ; un PDF individuel disparu est régénéré à la demande.
  L'en-tête des documents se configure avec les variables `ECOLE_*`.
