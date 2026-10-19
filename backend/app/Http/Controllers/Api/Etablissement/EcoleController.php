<?php

namespace App\Http\Controllers\Api\Etablissement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Etablissement\IdentiteRequest;
use App\Services\ParametresService;
use App\Support\EcoleCourante;
use Illuminate\Http\JsonResponse;

/** Identité de l'école courante : publique (écran de connexion), modifiable par la direction. */
class EcoleController extends Controller
{
    public function __construct(
        private readonly ParametresService $parametres,
        private readonly EcoleCourante $ecoles,
    ) {}

    public function show(): JsonResponse
    {
        return $this->reponse();
    }

    public function update(IdentiteRequest $request): JsonResponse
    {
        $this->parametres->definirIdentite($request->validated());

        return $this->reponse();
    }

    private function reponse(): JsonResponse
    {
        return response()->json(['data' => ['code' => $this->ecoles->get()?->code] + $this->parametres->identite()]);
    }
}
