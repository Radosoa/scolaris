<?php

namespace App\Http\Controllers\Api\Etablissement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Etablissement\ParametresRequest;
use App\Services\ParametresService;
use App\Support\Devise;
use Illuminate\Http\JsonResponse;

/** Réglages de l'établissement : lisibles par tout utilisateur connecté (affichage des montants). */
class ParametreController extends Controller
{
    public function __construct(private readonly ParametresService $parametres) {}

    public function show(): JsonResponse
    {
        return $this->reponse();
    }

    public function update(ParametresRequest $request): JsonResponse
    {
        $this->parametres->definirDevise($request->validated('devise'));

        return $this->reponse();
    }

    private function reponse(): JsonResponse
    {
        return response()->json(['data' => [
            'devise' => $this->parametres->devise()->toArray(),
            'devises' => array_map(fn (Devise $d) => $d->toArray(), Devise::toutes()),
        ]]);
    }
}
