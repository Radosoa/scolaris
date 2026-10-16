# Prompt Claude Code — ERP de gestion scolaire

> **Mode d'emploi** : crée un dossier vide pour le projet, ouvre Claude Code dedans, puis colle tout le contenu ci-dessous (à partir de « Contexte du projet »). Claude Code travaillera phase par phase et s'arrêtera après chacune pour que tu valides.

---

## Contexte du projet

Tu es un développeur full-stack senior. Nous allons construire ensemble **un ERP complet de gestion d'école** (élèves, professeurs, matières, notes, bulletins automatiques, absences, paiements). C'est un projet destiné à mon **portfolio** : le code doit être propre, bien structuré, testé et facile à présenter à un recruteur.

Travaille **phase par phase**. À la fin de chaque phase :
1. résume ce qui a été fait ;
2. indique les commandes pour lancer et tester ;
3. **arrête-toi et attends ma validation** avant de passer à la phase suivante.

Avant de commencer la phase 1, présente-moi ton plan (arborescence du projet et liste des tables) et attends mon accord.

---

## Stack technique (imposée)

### Backend — `backend/`
- **Laravel** (dernière version stable), en **API REST uniquement** (pas de vues Blade sauf pour les templates PDF).
- **MySQL** en local et en production.
- **Laravel Sanctum en mode token** (en-tête `Authorization: Bearer`), pas d'authentification par cookies : le front et l'API seront sur des domaines différents.
- **spatie/laravel-permission** pour les rôles et permissions.
- **barryvdh/laravel-dompdf** pour les bulletins et reçus PDF.
- **maatwebsite/excel** pour l'import d'élèves depuis un fichier Excel.
- Form Requests pour la validation, API Resources pour les réponses JSON, Policies pour les autorisations.
- Logique métier dans des **classes Service** (les contrôleurs restent fins).
- Tests avec **PHPUnit** (ou Pest).
- Queue driver : `database` (compatible avec un hébergement gratuit sans worker permanent).

### Frontend — `frontend/`
- **React + Vite + TypeScript**.
- **React Router** pour la navigation, avec routes protégées par rôle.
- **TanStack Query** pour les appels API et le cache.
- **Axios** avec un intercepteur qui ajoute le token et gère les erreurs 401.
- **React Hook Form + Zod** pour les formulaires.
- **Tailwind CSS + shadcn/ui** pour l'interface.
- **Recharts** pour les graphiques du tableau de bord.
- Interface en **français**, responsive, propre et professionnelle.

### Structure du dépôt (monorepo)
```
erp-ecole/
├── backend/      # API Laravel
├── frontend/     # SPA React
└── README.md     # présentation du projet
```

---

## Rôles utilisateurs

| Rôle | Accès principal |
|---|---|
| **admin** (direction) | Tout : configuration de l'année, classes, matières, coefficients, validation des bulletins, statistiques |
| **secretariat** | Inscriptions, dossiers élèves, parents, paiements, édition de documents |
| **professeur** | Ses classes uniquement : saisie des notes, absences, appréciations |
| **eleve** | Ses notes, son emploi du temps, ses bulletins |
| **parent** | Suivi de ses enfants : notes, absences, bulletins, paiements |

Un professeur ne doit **jamais** pouvoir voir ou modifier les notes d'une classe où il n'est pas affecté. Un parent ne voit **que** ses propres enfants. Ces règles doivent être couvertes par des Policies **et** par des tests.

---

## Modèle de données

Utilise ce schéma comme base (tu peux proposer des améliorations, en les justifiant) :

```
annee_scolaire (id, libelle, date_debut, date_fin, active)
periode (id, annee_id, libelle, ordre, date_debut, date_fin, cloturee)
niveau (id, libelle, ordre)
classe (id, niveau_id, annee_id, nom, capacite)
matiere (id, nom, code)
coefficient (id, niveau_id, matiere_id, valeur)        -- unique (niveau_id, matiere_id)
eleve (id, matricule, nom, prenom, sexe, date_naissance, lieu_naissance, photo, user_id nullable)
parent (id, nom, prenom, telephone, email, user_id nullable)
eleve_parent (eleve_id, parent_id, lien)
inscription (id, eleve_id, classe_id, annee_id, statut) -- unique (eleve_id, annee_id)
enseignant (id, nom, prenom, specialite, telephone, user_id)
affectation (id, enseignant_id, matiere_id, classe_id, annee_id)
evaluation (id, affectation_id, periode_id, type, titre, date, bareme)
   -- type : devoir | interrogation | composition
note (id, evaluation_id, eleve_id, valeur nullable, absent bool)
   -- unique (evaluation_id, eleve_id)
absence (id, eleve_id, date, heure_debut, heure_fin, type, justifiee, motif)
   -- type : absence | retard
creneau (id, classe_id, affectation_id, jour, heure_debut, heure_fin, salle)
bulletin (id, inscription_id, periode_id, moyenne, rang, effectif,
          mention, appreciation_generale, valide, pdf_path)
bulletin_ligne (id, bulletin_id, matiere_id, moyenne, coefficient,
                rang_matiere, appreciation)
frais (id, niveau_id, annee_id, libelle, montant)
paiement (id, inscription_id, montant, date, mode, reference)
annonce (id, auteur_id, titre, contenu, cible, date_publication)
```

Ajoute les clés étrangères, index et contraintes d'unicité appropriés.

---

## Règles métier du bulletin automatique (fonctionnalité phare)

Isole toute cette logique dans un service dédié `BulletinService` (et éventuellement `MoyenneCalculator`), **entièrement couvert par des tests unitaires**.

1. **Normalisation** : chaque note est ramenée sur 20 : `note / bareme × 20`.
2. **Moyenne par matière** sur une période, pondérée par type d'évaluation :
   - devoirs et interrogations : **40 %** (moyenne simple entre eux) ;
   - composition : **60 %** ;
   - si une catégorie est vide, la moyenne se calcule uniquement sur l'autre ;
   - ces pourcentages doivent être **configurables** (fichier de config ou table de paramètres).
3. Un élève marqué **absent** à une évaluation : la note n'est pas comptée (ni zéro, ni dans le diviseur). Si l'élève n'a aucune note dans une matière, la matière est exclue de sa moyenne générale.
4. **Moyenne générale** = Σ (moyenne matière × coefficient du niveau) / Σ coefficients des matières notées.
5. **Rang** : classement décroissant dans la classe, les **ex æquo** partagent le même rang (ex. : 1, 2, 2, 4).
6. Calculer aussi : moyenne de la classe, plus forte et plus faible moyenne, rang par matière, total des heures d'absence (justifiées / non justifiées) sur la période.
7. **Mention automatique** selon la moyenne générale (seuils configurables) :
   - ≥ 16 : Félicitations
   - ≥ 14 : Tableau d'honneur avec encouragements
   - ≥ 12 : Tableau d'honneur
   - ≥ 10 : Passable
   - < 10 : Avertissement travail
8. Arrondir les moyennes à **2 décimales** à l'affichage, mais calculer les rangs sur les valeurs non arrondies.
9. Le bulletin n'est générable que si la période est **clôturée** ; une fois **validé** par l'admin, il n'est plus modifiable.
10. **Génération PDF** : bulletin individuel et génération en lot pour toute une classe (en job de queue), avec en-tête de l'école, infos de l'élève, tableau des matières, moyennes, rang, mention, absences, appréciations et zones de signature.

Tests attendus au minimum : notes sur barèmes différents, élève absent à une évaluation, matière sans note, ex æquo, classe d'un seul élève, coefficients différents selon le niveau.

---

## Phases de développement

### Phase 1 — Fondations
- Initialisation du monorepo, `backend/` Laravel et `frontend/` React + Vite + TS.
- Configuration MySQL, Sanctum (token), CORS (origine du front configurable via `.env`).
- Rôles et permissions avec spatie.
- Authentification complète : login, logout, `GET /api/me` qui renvoie l'utilisateur et ses rôles.
- Front : page de connexion, layout avec sidebar adaptée au rôle, routes protégées.

### Phase 2 — Structure académique
- CRUD : années scolaires, périodes, niveaux, classes, matières, coefficients.
- Une seule année scolaire active à la fois.
- Écrans d'administration avec tableaux paginés, recherche et formulaires validés.

### Phase 3 — Élèves, parents, professeurs
- CRUD élèves (avec photo), parents (liaison multiple élève ↔ parent), enseignants.
- Inscription d'un élève dans une classe pour l'année active (vérifier la capacité).
- Affectations professeur ↔ matière ↔ classe.
- Import d'élèves depuis Excel avec rapport d'erreurs ligne par ligne.
- Génération automatique du matricule.

### Phase 4 — Évaluations et notes
- Le professeur crée des évaluations pour ses affectations.
- Écran de saisie des notes en **grille** (tous les élèves d'une classe, navigation clavier, sauvegarde en lot).
- Blocage de la saisie si la période est clôturée.

### Phase 5 — Bulletins automatiques
- `BulletinService` selon les règles ci-dessus + tests.
- Écran admin : choisir classe + période → calculer → prévisualiser → valider → générer les PDF.
- Le professeur peut saisir l'appréciation de sa matière ; l'admin saisit l'appréciation générale.
- Élèves et parents peuvent consulter et télécharger les bulletins validés.

### Phase 6 — Vie scolaire
- Appel numérique (absences / retards) par le professeur pour un créneau.
- Justification des absences par le secrétariat.
- Emploi du temps par classe et par professeur, avec **détection des conflits** (professeur, salle, classe sur un même créneau).

### Phase 7 — Finances et communication
- Frais par niveau, paiements par tranches, reçus PDF, liste des impayés.
- Annonces ciblées (tous, professeurs, parents, une classe).

### Phase 8 — Tableau de bord et finitions
- Dashboard admin : effectifs, répartition par classe et par sexe, moyennes par classe, taux de réussite, absentéisme, état des paiements (graphiques Recharts).
- Dashboard spécifique pour chaque rôle.
- **Seeders de démonstration** réalistes (noms français, une année complète, plusieurs classes, notes cohérentes) et **un compte de démo par rôle**.
- `README.md` soigné : présentation, captures d'écran (emplacements), fonctionnalités, schéma de la base, architecture, installation locale, identifiants de démo.

---

## Contraintes de déploiement (à prévoir dès le début)

- Frontend déployé sur **Vercel** ou **Netlify** : URL de l'API via variable `VITE_API_URL`, et configuration de réécriture pour que le routage SPA fonctionne au rafraîchissement.
- Backend déployé sur un **hébergement gratuit** (AlwaysData ou Render via Docker) : prévois un `Dockerfile` pour le backend, et ne dépends pas d'un worker permanent ni d'un cron obligatoire (les jobs doivent pouvoir s'exécuter en `sync` si nécessaire).
- Base **MySQL** distante : toute la configuration passe par `.env`, avec un `.env.example` complet et commenté.

---

## Qualité du code attendue

- Code et noms de tables cohérents (choisis le français ou l'anglais pour le code et garde le même partout ; l'interface reste en français).
- Pas de logique métier dans les contrôleurs ni dans les composants React.
- Réponses API homogènes (API Resources, codes HTTP corrects, messages d'erreur de validation en français).
- Pagination côté serveur pour toutes les listes.
- Composants React réutilisables (tableau de données, formulaire, modale de confirmation).
- Commits Git clairs et atomiques à chaque étape importante (format Conventional Commits).
- Signale-moi toute décision technique importante ou toute ambiguïté au lieu de la trancher en silence.

**Commence maintenant par me présenter ton plan pour la phase 1.**
