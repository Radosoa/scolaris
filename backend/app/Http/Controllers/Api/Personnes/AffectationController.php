<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personnes\AffectationRequest;
use App\Http\Resources\AffectationResource;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Services\AffectationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AffectationController extends Controller
{
    public function __construct(private readonly AffectationService $service) {}

    /**
     * Affectations de l'année active par défaut. Un professeur ne voit que les siennes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Affectation::class);

        $user = $request->user();
        $anneeId = $request->integer('annee_scolaire_id') ?: AnneeScolaire::active()->value('id');

        $affectations = Affectation::query()
            ->with(['enseignant', 'matiere', 'classe'])
            ->join('classes', 'classes.id', '=', 'affectations.classe_id')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->join('matieres', 'matieres.id', '=', 'affectations.matiere_id')
            ->select('affectations.*')
            ->where('classes.annee_scolaire_id', $anneeId)
            ->when(! $user->estAdministration() && $user->hasRole(Role::Professeur->value),
                fn (Builder $q) => $q->where('affectations.enseignant_id', $user->enseignant?->id ?? 0))
            ->when($request->integer('classe_id'), fn (Builder $q, $id) => $q->where('affectations.classe_id', $id))
            ->when($request->integer('enseignant_id'), fn (Builder $q, $id) => $q->where('affectations.enseignant_id', $id))
            ->orderBy('niveaux.ordre')
            ->orderBy('classes.nom')
            ->orderBy('matieres.nom')
            ->paginate($this->parPage($request));

        return AffectationResource::collection($affectations);
    }

    public function store(AffectationRequest $request): JsonResponse
    {
        $affectation = $this->service->enregistrer($request->validated());

        return (new AffectationResource($affectation->load(['enseignant', 'matiere', 'classe'])))
            ->response()->setStatusCode(201);
    }

    public function update(AffectationRequest $request, Affectation $affectation): AffectationResource
    {
        $affectation = $this->service->enregistrer($request->validated(), $affectation);

        return new AffectationResource($affectation->load(['enseignant', 'matiere', 'classe']));
    }

    /** Impossible si des évaluations ont déjà été créées pour cette affectation (409). */
    public function destroy(Affectation $affectation): Response
    {
        Gate::authorize('delete', $affectation);

        $affectation->delete();

        return response()->noContent();
    }
}
