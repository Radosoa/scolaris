<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnes\CompteRequest;
use App\Http\Requests\Personnes\TuteurRequest;
use App\Http\Resources\TuteurResource;
use App\Models\Tuteur;
use App\Services\CompteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TuteurController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tuteur::class);

        $tuteurs = Tuteur::query()
            ->recherche($request->string('q')->trim()->value())
            ->with(['eleves', 'user'])
            ->withCount('eleves')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($this->parPage($request));

        return TuteurResource::collection($tuteurs);
    }

    public function store(TuteurRequest $request): JsonResponse
    {
        $tuteur = Tuteur::create($request->validated());

        return (new TuteurResource($tuteur->load(['eleves', 'user'])))->response()->setStatusCode(201);
    }

    public function show(Tuteur $tuteur): TuteurResource
    {
        Gate::authorize('view', $tuteur);

        return new TuteurResource($tuteur->load(['eleves', 'user']));
    }

    public function update(TuteurRequest $request, Tuteur $tuteur): TuteurResource
    {
        $tuteur->update($request->validated());

        return new TuteurResource($tuteur->load(['eleves', 'user']));
    }

    /** Archive le parent : les liens avec ses enfants sont conservés pour l'historique. */
    public function destroy(Tuteur $tuteur): Response
    {
        Gate::authorize('delete', $tuteur);

        $tuteur->user?->forceFill(['actif' => false])->save();
        $tuteur->user?->tokens()->delete();
        $tuteur->delete();

        return response()->noContent();
    }

    public function creerCompte(CompteRequest $request, Tuteur $tuteur, CompteService $comptes): TuteurResource
    {
        $comptes->creerPourTuteur($tuteur, $request->validated('email'), $request->validated('password'));

        return new TuteurResource($tuteur->refresh()->load(['eleves', 'user']));
    }
}
