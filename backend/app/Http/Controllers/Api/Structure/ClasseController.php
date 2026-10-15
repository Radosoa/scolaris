<?php

namespace App\Http\Controllers\Api\Structure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\ClasseRequest;
use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use App\Services\ClasseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClasseController extends Controller
{
    public function __construct(private readonly ClasseService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Classe::class);

        $classes = $this->requeteAvecEffectif()
            ->with(['niveau', 'anneeScolaire'])
            ->recherche($request->string('q')->trim()->value())
            ->when($request->integer('annee_scolaire_id'), fn ($q, $id) => $q->where('classes.annee_scolaire_id', $id))
            ->when($request->integer('niveau_id'), fn ($q, $id) => $q->where('classes.niveau_id', $id))
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->orderBy('niveaux.ordre')
            ->orderBy('classes.nom')
            ->paginate($this->parPage($request));

        return ClasseResource::collection($classes);
    }

    public function store(ClasseRequest $request): JsonResponse
    {
        $classe = Classe::create($request->validated());

        return (new ClasseResource($this->charger($classe)))->response()->setStatusCode(201);
    }

    public function show(Classe $classe): ClasseResource
    {
        Gate::authorize('view', $classe);

        return new ClasseResource($this->charger($classe));
    }

    public function update(ClasseRequest $request, Classe $classe): ClasseResource
    {
        $classe = $this->service->modifier($classe, $request->validated());

        return new ClasseResource($this->charger($classe));
    }

    public function destroy(Classe $classe): Response
    {
        Gate::authorize('delete', $classe);

        $classe->delete();

        return response()->noContent();
    }

    /** @return Builder<Classe> */
    private function requeteAvecEffectif(): Builder
    {
        return Classe::query()->select('classes.*')->addSelect([
            'effectif' => DB::table('inscriptions')
                ->selectRaw('count(*)')
                ->whereColumn('inscriptions.classe_id', 'classes.id')
                ->where('inscriptions.statut', 'inscrit'),
        ]);
    }

    private function charger(Classe $classe): Classe
    {
        return $this->requeteAvecEffectif()->with(['niveau', 'anneeScolaire'])->findOrFail($classe->id);
    }
}
