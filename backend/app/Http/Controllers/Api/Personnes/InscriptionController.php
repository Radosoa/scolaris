<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Enums\StatutInscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personnes\InscriptionRequest;
use App\Http\Resources\InscriptionResource;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Services\InscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InscriptionController extends Controller
{
    public function __construct(private readonly InscriptionService $service) {}

    /** Inscriptions d'une année (l'année active par défaut), filtrables par classe et statut. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Inscription::class);

        $anneeId = $request->integer('annee_scolaire_id') ?: AnneeScolaire::active()->value('id');
        $terme = $request->string('q')->trim()->value();

        $inscriptions = Inscription::query()
            ->with(['eleve', 'classe'])
            ->where('inscriptions.annee_scolaire_id', $anneeId)
            ->when($request->integer('classe_id'), fn (Builder $q, $id) => $q->where('inscriptions.classe_id', $id))
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('statut', $request->enum('statut', StatutInscription::class)))
            ->when($terme, fn (Builder $q) => $q->whereHas('eleve', fn (Builder $e) => $e->recherche($terme)))
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->select('inscriptions.*')
            ->orderBy('eleves.nom')
            ->orderBy('eleves.prenom')
            ->paginate($this->parPage($request));

        return InscriptionResource::collection($inscriptions);
    }

    public function store(InscriptionRequest $request): JsonResponse
    {
        $inscription = $this->service->inscrire(
            Eleve::findOrFail($request->validated('eleve_id')),
            Classe::findOrFail($request->validated('classe_id')),
            $request->validated('date_inscription'),
        );

        return (new InscriptionResource($inscription->load(['eleve', 'classe', 'anneeScolaire'])))
            ->response()->setStatusCode(201);
    }

    public function update(InscriptionRequest $request, Inscription $inscription): InscriptionResource
    {
        $inscription = $this->service->modifier(
            $inscription,
            $request->validated('classe_id'),
            $request->enum('statut', StatutInscription::class),
        );

        return new InscriptionResource($inscription->load(['eleve', 'classe', 'anneeScolaire']));
    }

    /** Annule une inscription saisie par erreur (impossible si des paiements ou bulletins y sont liés). */
    public function destroy(Inscription $inscription): Response
    {
        Gate::authorize('delete', $inscription);

        $inscription->delete();

        return response()->noContent();
    }
}
