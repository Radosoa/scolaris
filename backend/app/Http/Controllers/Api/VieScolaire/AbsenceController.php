<?php

namespace App\Http\Controllers\Api\VieScolaire;

use App\Http\Controllers\Controller;
use App\Http\Requests\VieScolaire\JustificationRequest;
use App\Http\Resources\AbsenceResource;
use App\Models\Absence;
use App\Models\Eleve;
use App\Services\VieScolaire\AbsenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AbsenceController extends Controller
{
    private const RELATIONS = ['eleve', 'creneau.affectation.classe', 'creneau.affectation.matiere', 'creneau.affectation.enseignant'];

    public function __construct(private readonly AbsenceService $absences) {}

    /**
     * Suivi de la vie scolaire, filtrable : ?q= (élève), ?classe_id=, ?type=absence|retard,
     * ?statut=a_justifier|justifiees, ?du=, ?au=. Les plus récentes d'abord.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Absence::class);

        $requete = Absence::query()
            ->with(self::RELATIONS)
            ->when($request->string('q')->trim()->value(), fn (Builder $q, string $terme) => $q->whereHas('eleve', fn (Builder $e) => $e->recherche($terme)))
            ->when($request->integer('classe_id'), fn (Builder $q, int $id) => $q->whereHas('creneau.affectation', fn (Builder $a) => $a->where('classe_id', $id)))
            ->when(in_array($request->input('type'), ['absence', 'retard'], true), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->input('statut') === 'a_justifier', fn (Builder $q) => $q->where('justifiee', false))
            ->when($request->input('statut') === 'justifiees', fn (Builder $q) => $q->where('justifiee', true))
            ->when($request->date('du'), fn (Builder $q, $du) => $q->whereDate('date', '>=', $du))
            ->when($request->date('au'), fn (Builder $q, $au) => $q->whereDate('date', '<=', $au));

        $aJustifier = (clone $requete)->where('justifiee', false)->count();

        return AbsenceResource::collection(
            $requete->orderByDesc('date')->orderByDesc('heure_debut')->paginate($this->parPage($request, 20))
        )->additional(['meta' => ['a_justifier' => $aJustifier]]);
    }

    public function justifier(JustificationRequest $request, Absence $absence): JsonResponse
    {
        $nombre = $this->absences->justifier(
            $absence,
            $request->boolean('justifiee'),
            $request->validated('motif'),
            $request->boolean('toute_la_journee'),
        );

        return response()->json([
            'data' => new AbsenceResource($absence->refresh()->load(self::RELATIONS)),
            'meta' => ['mises_a_jour' => $nombre],
        ]);
    }

    /** Absences d'un élève (lui-même, ses parents, ses professeurs, la vie scolaire), avec les totaux. */
    public function deLEleve(Request $request, Eleve $eleve): AnonymousResourceCollection
    {
        Gate::authorize('view', $eleve);

        return AbsenceResource::collection(
            Absence::where('eleve_id', $eleve->id)
                ->with(self::RELATIONS)
                ->orderByDesc('date')
                ->orderByDesc('heure_debut')
                ->paginate($this->parPage($request, 20))
        )->additional(['totaux' => $this->absences->totaux($eleve)]);
    }
}
