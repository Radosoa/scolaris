<?php

namespace App\Http\Controllers\Api\Structure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\AnneeScolaireRequest;
use App\Http\Resources\AnneeScolaireResource;
use App\Models\AnneeScolaire;
use App\Services\AnneeScolaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AnneeScolaireController extends Controller
{
    public function __construct(private readonly AnneeScolaireService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AnneeScolaire::class);

        $annees = AnneeScolaire::query()
            ->recherche($request->string('q')->trim()->value())
            ->withCount(['periodes', 'classes'])
            ->orderByDesc('date_debut')
            ->paginate($this->parPage($request));

        return AnneeScolaireResource::collection($annees);
    }

    public function store(AnneeScolaireRequest $request): JsonResponse
    {
        $annee = $this->service->creer(
            $request->safe()->except('active'),
            $request->boolean('active'),
        );

        return (new AnneeScolaireResource($annee))->response()->setStatusCode(201);
    }

    public function show(AnneeScolaire $annee): AnneeScolaireResource
    {
        Gate::authorize('view', $annee);

        return new AnneeScolaireResource($annee->load('periodes')->loadCount(['periodes', 'classes']));
    }

    public function update(AnneeScolaireRequest $request, AnneeScolaire $annee): AnneeScolaireResource
    {
        return new AnneeScolaireResource($this->service->modifier($annee, $request->validated()));
    }

    public function destroy(AnneeScolaire $annee): Response
    {
        Gate::authorize('delete', $annee);

        $this->service->supprimer($annee);

        return response()->noContent();
    }

    public function activer(AnneeScolaire $annee): AnneeScolaireResource
    {
        Gate::authorize('update', $annee);

        return new AnneeScolaireResource($this->service->activer($annee));
    }

    /** Année active avec ses périodes, ou 404 si aucune n'est configurée. */
    public function active(): AnneeScolaireResource|JsonResponse
    {
        Gate::authorize('viewAny', AnneeScolaire::class);

        $annee = $this->service->anneeActive();

        if (! $annee) {
            return response()->json(['message' => 'Aucune année scolaire active.'], 404);
        }

        return new AnneeScolaireResource($annee->load('periodes'));
    }
}
