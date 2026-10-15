<?php

namespace App\Http\Controllers\Api\Structure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\CoefficientsRequest;
use App\Http\Requests\Structure\NiveauRequest;
use App\Http\Resources\NiveauResource;
use App\Models\Niveau;
use App\Services\CoefficientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class NiveauController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Niveau::class);

        $niveaux = Niveau::query()
            ->recherche($request->string('q')->trim()->value())
            ->withCount(['classes', 'coefficients'])
            ->orderBy('ordre')
            ->paginate($this->parPage($request));

        return NiveauResource::collection($niveaux);
    }

    public function store(NiveauRequest $request): JsonResponse
    {
        $niveau = Niveau::create($request->validated());

        return (new NiveauResource($niveau))->response()->setStatusCode(201);
    }

    public function show(Niveau $niveau): NiveauResource
    {
        Gate::authorize('view', $niveau);

        return new NiveauResource($niveau->loadCount(['classes', 'coefficients']));
    }

    public function update(NiveauRequest $request, Niveau $niveau): NiveauResource
    {
        $niveau->update($request->validated());

        return new NiveauResource($niveau);
    }

    public function destroy(Niveau $niveau): Response
    {
        Gate::authorize('delete', $niveau);

        $niveau->delete();

        return response()->noContent();
    }

    /** Grille des coefficients : toutes les matières, avec leur coefficient pour ce niveau. */
    public function coefficients(Niveau $niveau, CoefficientService $service): JsonResponse
    {
        Gate::authorize('view', $niveau);

        return response()->json(['data' => $service->grille($niveau)]);
    }

    public function synchroniserCoefficients(CoefficientsRequest $request, Niveau $niveau, CoefficientService $service): JsonResponse
    {
        $service->synchroniser($niveau, $request->validated('coefficients'));

        return response()->json(['data' => $service->grille($niveau)]);
    }
}
