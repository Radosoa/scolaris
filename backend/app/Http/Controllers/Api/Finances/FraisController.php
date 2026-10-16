<?php

namespace App\Http\Controllers\Api\Finances;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\EcheancesRequest;
use App\Http\Requests\Finances\FraisRequest;
use App\Models\AnneeScolaire;
use App\Models\Echeance;
use App\Models\Frais;
use App\Models\Niveau;
use App\Services\Finances\FinancesService;
use App\Services\Finances\FraisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FraisController extends Controller
{
    public function __construct(private readonly FraisService $frais) {}

    /** Frais de l'année (?annee_scolaire_id, active par défaut) regroupés par niveau, et échéancier. */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Frais::class);

        $annee = $request->integer('annee_scolaire_id')
            ? AnneeScolaire::findOrFail($request->integer('annee_scolaire_id'))
            : AnneeScolaire::active()->firstOrFail();

        $frais = Frais::where('annee_scolaire_id', $annee->id)->orderBy('libelle')->get()->groupBy('niveau_id');

        return response()->json([
            'data' => Niveau::orderBy('ordre')->get()->map(fn (Niveau $niveau) => [
                'niveau' => ['id' => $niveau->id, 'libelle' => $niveau->libelle],
                'frais' => ($frais[$niveau->id] ?? collect())->map(fn (Frais $f) => $this->ligne($f))->values(),
                'total' => FinancesService::euros(FinancesService::centimes(($frais[$niveau->id] ?? collect())->sum('montant'))),
            ]),
            'meta' => [
                'annee' => ['id' => $annee->id, 'libelle' => $annee->libelle, 'date_debut' => $annee->date_debut->toDateString(), 'date_fin' => $annee->date_fin->toDateString()],
                'echeances' => $this->echeancesDe($annee),
            ],
        ]);
    }

    public function store(FraisRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->ligne($this->frais->creer($request->validated()))], 201);
    }

    public function update(FraisRequest $request, Frais $frais): JsonResponse
    {
        return response()->json(['data' => $this->ligne($this->frais->modifier($frais, $request->safe()->only(['libelle', 'montant'])))]);
    }

    public function destroy(Frais $frais): Response
    {
        Gate::authorize('delete', $frais);

        $this->frais->supprimer($frais);

        return response()->noContent();
    }

    public function echeances(EcheancesRequest $request, AnneeScolaire $annee): JsonResponse
    {
        $this->frais->synchroniserEcheances($annee, $request->validated('echeances'));

        return response()->json(['data' => $this->echeancesDe($annee)]);
    }

    /** @return array<string, mixed> */
    private function ligne(Frais $frais): array
    {
        return [
            'id' => $frais->id,
            'niveau_id' => $frais->niveau_id,
            'annee_scolaire_id' => $frais->annee_scolaire_id,
            'libelle' => $frais->libelle,
            'montant' => (float) $frais->montant,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function echeancesDe(AnneeScolaire $annee): array
    {
        return Echeance::where('annee_scolaire_id', $annee->id)->orderBy('date_limite')->get()
            ->map(fn (Echeance $e) => [
                'id' => $e->id,
                'libelle' => $e->libelle,
                'date_limite' => $e->date_limite->toDateString(),
                'pourcentage' => $e->pourcentage,
            ])->all();
    }
}
