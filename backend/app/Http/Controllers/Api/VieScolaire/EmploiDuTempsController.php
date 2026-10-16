<?php

namespace App\Http\Controllers\Api\VieScolaire;

use App\Http\Controllers\Controller;
use App\Http\Requests\VieScolaire\CreneauRequest;
use App\Http\Resources\CreneauResource;
use App\Models\Creneau;
use App\Services\VieScolaire\EmploiDuTempsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EmploiDuTempsController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $emploiDuTemps) {}

    /**
     * Semaine type d'une classe (?classe_id) ou d'un professeur (?enseignant_id).
     * Une semaine tient sur un écran : pas de pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $edt = $this->emploiDuTemps->pour($request->user(), $request->integer('classe_id') ?: null, $request->integer('enseignant_id') ?: null);

        return response()->json([
            'data' => CreneauResource::collection($edt['creneaux']),
            'meta' => ['type' => $edt['type'], 'id' => $edt['id'], 'libelle' => $edt['libelle'], 'jours' => Creneau::JOURS],
        ]);
    }

    public function store(CreneauRequest $request): JsonResponse
    {
        $creneau = $this->emploiDuTemps->creer($request->validated());

        return (new CreneauResource($creneau->load('affectation.classe', 'affectation.matiere', 'affectation.enseignant')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(CreneauRequest $request, Creneau $creneau): CreneauResource
    {
        $creneau = $this->emploiDuTemps->modifier($creneau, $request->validated());

        return new CreneauResource($creneau->load('affectation.classe', 'affectation.matiere', 'affectation.enseignant'));
    }

    public function destroy(Creneau $creneau): Response
    {
        Gate::authorize('delete', $creneau);

        $this->emploiDuTemps->supprimer($creneau);

        return response()->noContent();
    }
}
