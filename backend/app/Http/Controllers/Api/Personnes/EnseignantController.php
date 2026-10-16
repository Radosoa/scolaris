<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnes\EnseignantRequest;
use App\Http\Resources\EnseignantResource;
use App\Models\Enseignant;
use App\Services\EnseignantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EnseignantController extends Controller
{
    public function __construct(private readonly EnseignantService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Enseignant::class);

        $enseignants = Enseignant::query()
            ->recherche($request->string('q')->trim()->value())
            ->with('user')
            // Nombre d'affectations sur l'année active
            ->withCount(['affectations' => fn (Builder $q) => $q->whereHas('classe.anneeScolaire', fn (Builder $a) => $a->where('active', true))])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($this->parPage($request));

        return EnseignantResource::collection($enseignants);
    }

    public function store(EnseignantRequest $request): JsonResponse
    {
        $enseignant = $this->service->creer(
            $request->safe()->except(['email', 'password']),
            $request->validated('email'),
            $request->validated('password'),
        );

        return (new EnseignantResource($this->charger($enseignant)))->response()->setStatusCode(201);
    }

    public function show(Enseignant $enseignant): EnseignantResource
    {
        Gate::authorize('view', $enseignant);

        return new EnseignantResource($this->charger($enseignant));
    }

    public function update(EnseignantRequest $request, Enseignant $enseignant): EnseignantResource
    {
        $enseignant = $this->service->modifier($enseignant, $request->safe()->except('email'), $request->validated('email'));

        return new EnseignantResource($this->charger($enseignant));
    }

    public function destroy(Enseignant $enseignant): Response
    {
        Gate::authorize('delete', $enseignant);

        $this->service->supprimer($enseignant);

        return response()->noContent();
    }

    private function charger(Enseignant $enseignant): Enseignant
    {
        return $enseignant->load([
            'user',
            'affectations' => fn ($q) => $q->with(['matiere', 'classe'])
                ->whereHas('classe.anneeScolaire', fn (Builder $a) => $a->where('active', true)),
        ]);
    }
}
