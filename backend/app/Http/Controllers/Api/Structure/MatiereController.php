<?php

namespace App\Http\Controllers\Api\Structure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\MatiereRequest;
use App\Http\Resources\MatiereResource;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MatiereController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Matiere::class);

        $matieres = Matiere::query()
            ->recherche($request->string('q')->trim()->value())
            ->withCount('coefficients')
            ->orderBy('nom')
            ->paginate($this->parPage($request));

        return MatiereResource::collection($matieres);
    }

    public function store(MatiereRequest $request): JsonResponse
    {
        $matiere = Matiere::create($request->validated());

        return (new MatiereResource($matiere))->response()->setStatusCode(201);
    }

    public function show(Matiere $matiere): MatiereResource
    {
        Gate::authorize('view', $matiere);

        return new MatiereResource($matiere->loadCount('coefficients'));
    }

    public function update(MatiereRequest $request, Matiere $matiere): MatiereResource
    {
        $matiere->update($request->validated());

        return new MatiereResource($matiere);
    }

    public function destroy(Matiere $matiere): Response
    {
        Gate::authorize('delete', $matiere);

        $matiere->delete();

        return response()->noContent();
    }
}
