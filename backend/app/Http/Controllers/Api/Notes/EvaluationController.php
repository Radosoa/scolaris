<?php

namespace App\Http\Controllers\Api\Notes;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notes\EvaluationRequest;
use App\Http\Requests\Notes\NotesRequest;
use App\Http\Resources\EvaluationResource;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Services\EvaluationService;
use App\Services\NoteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluations,
        private readonly NoteService $notes,
    ) {}

    /** Évaluations d'une affectation (et éventuellement d'une période). Un professeur ne voit que les siennes. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Evaluation::class);

        $user = $request->user();

        $evaluations = Evaluation::query()
            ->with('periode')
            ->withCount('notes')
            ->withAvg(['notes' => fn (Builder $q) => $q->where('absent', false)], 'valeur')
            ->when(! $user->hasRole(Role::Admin->value), fn (Builder $q) => $q->whereHas(
                'affectation', fn (Builder $a) => $a->where('enseignant_id', $user->enseignant?->id ?? 0)
            ))
            ->when($request->integer('affectation_id'), fn (Builder $q, $id) => $q->where('affectation_id', $id))
            ->when($request->integer('periode_id'), fn (Builder $q, $id) => $q->where('periode_id', $id))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($this->parPage($request, 50));

        return EvaluationResource::collection($evaluations);
    }

    public function store(EvaluationRequest $request): JsonResponse
    {
        $evaluation = $this->evaluations->creer(
            Affectation::with('classe')->findOrFail($request->validated('affectation_id')),
            $request->safe()->except('affectation_id'),
        );

        return (new EvaluationResource($this->charger($evaluation)))->response()->setStatusCode(201);
    }

    public function show(Evaluation $evaluation): EvaluationResource
    {
        Gate::authorize('view', $evaluation);

        return new EvaluationResource($this->charger($evaluation));
    }

    public function update(EvaluationRequest $request, Evaluation $evaluation): EvaluationResource
    {
        return new EvaluationResource($this->charger($this->evaluations->modifier($evaluation, $request->validated())));
    }

    public function destroy(Evaluation $evaluation): Response
    {
        Gate::authorize('delete', $evaluation);

        $this->evaluations->supprimer($evaluation);

        return response()->noContent();
    }

    /** Grille de saisie : évaluation, état de verrouillage, élèves et notes, statistiques. */
    public function grille(Evaluation $evaluation): JsonResponse
    {
        Gate::authorize('view', $evaluation);

        return response()->json(['data' => [
            'evaluation' => new EvaluationResource($this->charger($evaluation)),
            'eleves' => $this->notes->grille($evaluation),
            'statistiques' => $this->notes->statistiques($evaluation),
        ]]);
    }

    public function enregistrerNotes(NotesRequest $request, Evaluation $evaluation): JsonResponse
    {
        $this->notes->enregistrerLot($evaluation, $request->validated('notes'));

        return $this->grille($evaluation->refresh());
    }

    private function charger(Evaluation $evaluation): Evaluation
    {
        return $evaluation->load(['periode', 'affectation.matiere', 'affectation.classe', 'affectation.enseignant']);
    }
}
