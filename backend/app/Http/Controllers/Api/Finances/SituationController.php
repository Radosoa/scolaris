<?php

namespace App\Http\Controllers\Api\Finances;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementResource;
use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\Finances\FinancesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SituationController extends Controller
{
    public function __construct(private readonly FinancesService $finances) {}

    /** Situation financière d'un élève pour l'année active : frais, échéancier, paiements (annulés compris). */
    public function eleve(Eleve $eleve): JsonResponse
    {
        Gate::authorize('voirSituation', [Paiement::class, $eleve]);

        $inscription = $eleve->inscriptionActive()->with('classe', 'anneeScolaire')->first();
        if (! $inscription) {
            return response()->json(['message' => 'Cet élève n\'est pas inscrit cette année.'], 404);
        }

        return response()->json(['data' => [
            'inscription_id' => $inscription->id,
            'eleve' => ['id' => $eleve->id, 'matricule' => $eleve->matricule, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'classe' => ['id' => $inscription->classe->id, 'nom' => $inscription->classe->nom],
            'annee' => $inscription->anneeScolaire->libelle,
            ...$this->finances->situation($inscription),
            'paiements' => PaiementResource::collection(
                Paiement::where('inscription_id', $inscription->id)->with('encaissePar')->orderByDesc('date')->orderByDesc('id')->get()
            ),
        ]]);
    }

    /**
     * Soldes de l'année active, du plus en retard au plus à jour.
     * ?statut=en_retard (exigible non réglé) | reste (reste à payer, défaut) | solde (à jour) | tous ;
     * ?classe_id= ; ?q= (élève). Les totaux de l'année sont renvoyés dans meta.
     */
    public function impayes(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Paiement::class);

        $annee = AnneeScolaire::active()->firstOrFail();
        $pourcentage = $this->finances->pourcentageExigible($annee->id, CarbonImmutable::today());
        $exigible = 'ROUND(total_frais * '.((float) $pourcentage).' / 100, 2)';

        $page = $this->finances->soldes($annee)
            ->select('inscriptions.*')
            ->selectRaw('GREATEST(total_frais - total_paye, 0) AS reste')
            ->selectRaw("GREATEST({$exigible} - total_paye, 0) AS retard")
            ->with(['eleve', 'classe'])
            ->when($request->integer('classe_id'), fn (Builder $q, int $id) => $q->where('classe_id', $id))
            ->when($request->string('q')->trim()->value(), fn (Builder $q, string $terme) => $q->whereHas('eleve', fn (Builder $e) => $e->recherche($terme)))
            ->when(match ($request->input('statut', 'reste')) {
                'en_retard' => "{$exigible} - total_paye > 0.004",
                'reste' => 'total_frais - total_paye > 0.004',
                'solde' => 'total_frais - total_paye <= 0.004',
                default => null,
            }, fn (Builder $q, string $condition) => $q->whereRaw($condition))
            ->orderByDesc('retard')
            ->orderByDesc('reste')
            ->orderBy('eleve_nom')
            ->paginate($this->parPage($request, 20));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Inscription $i) => [
                'inscription_id' => $i->id,
                'eleve' => ['id' => $i->eleve->id, 'matricule' => $i->eleve->matricule, 'nom' => $i->eleve->nom, 'prenom' => $i->eleve->prenom],
                'classe' => ['id' => $i->classe->id, 'nom' => $i->classe->nom],
                'total' => (float) $i->total_frais,
                'paye' => (float) $i->total_paye,
                'reste' => (float) $i->reste,
                'retard' => (float) $i->retard,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'totaux' => $this->finances->totauxAnnee($annee),
            ],
        ]);
    }
}
