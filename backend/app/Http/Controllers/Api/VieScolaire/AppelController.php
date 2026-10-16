<?php

namespace App\Http\Controllers\Api\VieScolaire;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\VieScolaire\AppelRequest;
use App\Http\Resources\CreneauResource;
use App\Models\Appel;
use App\Models\Creneau;
use App\Services\VieScolaire\AppelService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppelController extends Controller
{
    public function __construct(private readonly AppelService $appels) {}

    /** Cours du professeur à une date (aujourd'hui par défaut), avec l'état de l'appel. */
    public function jour(Request $request): JsonResponse
    {
        Gate::authorize(Permission::AbsencesSaisir->value);
        $date = $this->date($request);

        return response()->json([
            'data' => $this->appels->creneauxDuJour($request->user(), $date)->map(fn (array $ligne) => [
                'creneau' => new CreneauResource($ligne['creneau']),
                'appel_fait' => $ligne['appel'] !== null,
                'appel_fait_le' => $ligne['appel']?->updated_at?->toIso8601String(),
            ]),
            'meta' => ['date' => $date->toDateString()],
        ]);
    }

    public function show(Request $request, Creneau $creneau): JsonResponse
    {
        Gate::authorize('faireAppel', $creneau);

        return $this->feuille($creneau, $this->date($request));
    }

    public function update(AppelRequest $request, Creneau $creneau): JsonResponse
    {
        $date = CarbonImmutable::parse($request->validated('date'));
        $this->appels->enregistrer($creneau, $date, $request->validated('eleves'), $request->user());

        return $this->feuille($creneau, $date);
    }

    private function feuille(Creneau $creneau, CarbonImmutable $date): JsonResponse
    {
        $feuille = $this->appels->feuille($creneau, $date);
        /** @var Appel|null $appel */
        $appel = $feuille['appel'];

        return response()->json(['data' => [
            'creneau' => new CreneauResource($creneau->load('affectation.classe', 'affectation.matiere', 'affectation.enseignant')),
            'date' => $date->toDateString(),
            'appel' => $appel ? ['fait_le' => $appel->updated_at->toIso8601String(), 'fait_par' => $appel->faitPar?->name] : null,
            'eleves' => $feuille['eleves'],
        ]]);
    }

    private function date(Request $request): CarbonImmutable
    {
        $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        return $request->filled('date') ? CarbonImmutable::parse($request->input('date')) : CarbonImmutable::today();
    }
}
