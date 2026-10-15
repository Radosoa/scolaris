<?php

namespace App\Http\Controllers\Api\Structure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\PeriodeRequest;
use App\Http\Resources\PeriodeResource;
use App\Models\Periode;
use App\Services\PeriodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PeriodeController extends Controller
{
    public function __construct(private readonly PeriodeService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Periode::class);

        $periodes = Periode::query()
            ->when($request->integer('annee_scolaire_id'), fn ($q, $id) => $q->where('annee_scolaire_id', $id))
            ->orderBy('annee_scolaire_id')
            ->orderBy('ordre')
            ->paginate($this->parPage($request));

        return PeriodeResource::collection($periodes);
    }

    public function store(PeriodeRequest $request): JsonResponse
    {
        $periode = $this->service->creer($request->validated());

        return (new PeriodeResource($periode))->response()->setStatusCode(201);
    }

    public function show(Periode $periode): PeriodeResource
    {
        Gate::authorize('view', $periode);

        return new PeriodeResource($periode);
    }

    public function update(PeriodeRequest $request, Periode $periode): PeriodeResource
    {
        return new PeriodeResource($this->service->modifier($periode, $request->validated()));
    }

    public function destroy(Periode $periode): Response
    {
        Gate::authorize('delete', $periode);

        $this->service->supprimer($periode);

        return response()->noContent();
    }

    public function cloturer(Periode $periode): PeriodeResource
    {
        Gate::authorize('update', $periode);

        return new PeriodeResource($this->service->cloturer($periode));
    }

    public function rouvrir(Periode $periode): PeriodeResource
    {
        Gate::authorize('update', $periode);

        return new PeriodeResource($this->service->rouvrir($periode));
    }
}
