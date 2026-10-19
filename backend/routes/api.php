<?php

use App\Http\Controllers\Api\AnnonceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Bulletins\AppreciationController;
use App\Http\Controllers\Api\Bulletins\BulletinClasseController;
use App\Http\Controllers\Api\Bulletins\BulletinController;
use App\Http\Controllers\Api\Etablissement\EcoleController;
use App\Http\Controllers\Api\Etablissement\ParametreController;
use App\Http\Controllers\Api\Finances\FraisController;
use App\Http\Controllers\Api\Finances\PaiementController;
use App\Http\Controllers\Api\Finances\SituationController;
use App\Http\Controllers\Api\Notes\EvaluationController;
use App\Http\Controllers\Api\Notes\ReleveNotesController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Personnes\AffectationController;
use App\Http\Controllers\Api\Personnes\EleveController;
use App\Http\Controllers\Api\Personnes\EnseignantController;
use App\Http\Controllers\Api\Personnes\ImportEleveController;
use App\Http\Controllers\Api\Personnes\InscriptionController;
use App\Http\Controllers\Api\Personnes\MesEnfantsController;
use App\Http\Controllers\Api\Personnes\TuteurController;
use App\Http\Controllers\Api\Structure\AnneeScolaireController;
use App\Http\Controllers\Api\Structure\ClasseController;
use App\Http\Controllers\Api\Structure\MatiereController;
use App\Http\Controllers\Api\Structure\NiveauController;
use App\Http\Controllers\Api\Structure\PeriodeController;
use App\Http\Controllers\Api\TableauDeBordController;
use App\Http\Controllers\Api\VieScolaire\AbsenceController;
use App\Http\Controllers\Api\VieScolaire\AppelController;
use App\Http\Controllers\Api\VieScolaire\EmploiDuTempsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Identité de l'école désignée par X-Ecole (écran de connexion, menu)
Route::get('/ecole', [EcoleController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/tableau-de-bord', TableauDeBordController::class);

    // --- Structure académique ---------------------------------------------
    Route::get('/annee-active', [AnneeScolaireController::class, 'active']);
    Route::apiResource('annees-scolaires', AnneeScolaireController::class)->parameters(['annees-scolaires' => 'annee']);
    Route::post('/annees-scolaires/{annee}/activer', [AnneeScolaireController::class, 'activer']);

    Route::apiResource('periodes', PeriodeController::class);
    Route::post('/periodes/{periode}/cloturer', [PeriodeController::class, 'cloturer']);
    Route::post('/periodes/{periode}/rouvrir', [PeriodeController::class, 'rouvrir']);

    Route::apiResource('niveaux', NiveauController::class)->parameters(['niveaux' => 'niveau']);
    Route::get('/niveaux/{niveau}/coefficients', [NiveauController::class, 'coefficients']);
    Route::put('/niveaux/{niveau}/coefficients', [NiveauController::class, 'synchroniserCoefficients']);

    Route::apiResource('classes', ClasseController::class)->parameters(['classes' => 'classe']);
    Route::apiResource('matieres', MatiereController::class)->parameters(['matieres' => 'matiere']);

    // --- Élèves, parents, enseignants ---------------------------------------
    Route::get('/eleves/import/modele', [ImportEleveController::class, 'modele']);
    Route::post('/eleves/import/analyse', [ImportEleveController::class, 'analyser']);
    Route::post('/eleves/import', [ImportEleveController::class, 'importer']);
    Route::apiResource('eleves', EleveController::class)->parameters(['eleves' => 'eleve']);
    Route::post('/eleves/{eleve}/photo', [EleveController::class, 'definirPhoto']);
    Route::delete('/eleves/{eleve}/photo', [EleveController::class, 'supprimerPhoto']);
    Route::post('/eleves/{eleve}/compte', [EleveController::class, 'creerCompte']);

    Route::apiResource('tuteurs', TuteurController::class);
    Route::post('/tuteurs/{tuteur}/compte', [TuteurController::class, 'creerCompte']);

    Route::apiResource('enseignants', EnseignantController::class);
    Route::apiResource('inscriptions', InscriptionController::class)->except('show');
    Route::apiResource('affectations', AffectationController::class)->except('show');

    Route::get('/moi/enfants', MesEnfantsController::class);

    // --- Évaluations et notes ------------------------------------------------
    Route::apiResource('evaluations', EvaluationController::class);
    Route::get('/evaluations/{evaluation}/notes', [EvaluationController::class, 'grille']);
    Route::put('/evaluations/{evaluation}/notes', [EvaluationController::class, 'enregistrerNotes']);
    Route::get('/eleves/{eleve}/notes', ReleveNotesController::class);

    // --- Bulletins ------------------------------------------------------------
    Route::prefix('/classes/{classe}/periodes/{periode}/bulletins')->controller(BulletinClasseController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/calculer', 'calculer');
        Route::post('/valider', 'valider');
        Route::post('/pdf', 'genererPdf');
        Route::get('/pdf', 'telechargerPdf');
    });
    Route::get('/bulletins/{bulletin}', [BulletinController::class, 'show']);
    Route::put('/bulletins/{bulletin}/appreciation', [BulletinController::class, 'appreciation']);
    Route::get('/bulletins/{bulletin}/pdf', [BulletinController::class, 'pdf']);
    Route::get('/eleves/{eleve}/bulletins', [BulletinController::class, 'deLEleve']);
    Route::get('/affectations/{affectation}/appreciations', [AppreciationController::class, 'index']);
    Route::put('/affectations/{affectation}/appreciations', [AppreciationController::class, 'enregistrer']);

    // --- Vie scolaire --------------------------------------------------------
    Route::get('/emploi-du-temps', [EmploiDuTempsController::class, 'index']);
    Route::apiResource('creneaux', EmploiDuTempsController::class)->only(['store', 'update', 'destroy'])->parameters(['creneaux' => 'creneau']);
    Route::get('/appels', [AppelController::class, 'jour']);
    Route::get('/creneaux/{creneau}/appel', [AppelController::class, 'show']);
    Route::put('/creneaux/{creneau}/appel', [AppelController::class, 'update']);
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::put('/absences/{absence}/justification', [AbsenceController::class, 'justifier']);
    Route::get('/eleves/{eleve}/absences', [AbsenceController::class, 'deLEleve']);

    // --- Finances ------------------------------------------------------------
    Route::apiResource('frais', FraisController::class)->except('show')->parameters(['frais' => 'frais']);
    Route::put('/annees-scolaires/{annee}/echeances', [FraisController::class, 'echeances']);
    Route::get('/impayes', [SituationController::class, 'impayes']);
    Route::get('/eleves/{eleve}/finances', [SituationController::class, 'eleve']);
    Route::get('/paiements', [PaiementController::class, 'index']);
    Route::post('/inscriptions/{inscription}/paiements', [PaiementController::class, 'store']);
    Route::post('/paiements/{paiement}/annuler', [PaiementController::class, 'annuler']);
    Route::get('/paiements/{paiement}/recu', [PaiementController::class, 'recu']);

    // --- Communication -------------------------------------------------------
    Route::apiResource('annonces', AnnonceController::class);

    // --- Établissement -------------------------------------------------------
    Route::get('/parametres', [ParametreController::class, 'show']);
    Route::put('/parametres', [ParametreController::class, 'update']);
    Route::put('/ecole', [EcoleController::class, 'update']);

    // --- Notifications -------------------------------------------------------
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/tout-lire', [NotificationController::class, 'toutLire']);
    Route::post('/notifications/{id}/lire', [NotificationController::class, 'lire']);
});

// Photo d'élève : URL signée et temporaire générée par EleveResource (balise <img>, sans token)
Route::get('/eleves/{eleve}/photo', [EleveController::class, 'photo'])
    ->middleware('signed')
    ->name('eleves.photo');
