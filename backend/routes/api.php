<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Structure\AnneeScolaireController;
use App\Http\Controllers\Api\Structure\ClasseController;
use App\Http\Controllers\Api\Structure\MatiereController;
use App\Http\Controllers\Api\Structure\NiveauController;
use App\Http\Controllers\Api\Structure\PeriodeController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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
});
