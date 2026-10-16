<?php

namespace App\Http\Controllers\Api\Finances;

use App\Enums\ModePaiement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\PaiementRequest;
use App\Http\Resources\PaiementResource;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\Finances\PaiementService;
use App\Services\Finances\RecuPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PaiementController extends Controller
{
    public function __construct(
        private readonly PaiementService $paiements,
        private readonly RecuPdfService $recus,
    ) {}

    /**
     * Journal des encaissements : ?du=, ?au=, ?mode=, ?q= (élève ou n° de reçu), ?classe_id=.
     * Le total encaissé de la sélection (hors annulés) est renvoyé dans meta.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Paiement::class);

        $requete = Paiement::query()
            ->with(['inscription.eleve', 'inscription.classe', 'encaissePar'])
            ->when($request->date('du'), fn (Builder $q, $du) => $q->whereDate('date', '>=', $du))
            ->when($request->date('au'), fn (Builder $q, $au) => $q->whereDate('date', '<=', $au))
            ->when(ModePaiement::tryFrom((string) $request->input('mode')), fn (Builder $q, ModePaiement $mode) => $q->where('mode', $mode))
            ->when($request->integer('classe_id'), fn (Builder $q, int $id) => $q->whereHas('inscription', fn (Builder $i) => $i->where('classe_id', $id)))
            ->when($request->string('q')->trim()->value(), fn (Builder $q, string $terme) => $q->where(fn (Builder $sous) => $sous
                ->where('numero_recu', 'like', "%{$terme}%")
                ->orWhereHas('inscription.eleve', fn (Builder $e) => $e->recherche($terme))));

        $encaisse = (float) (clone $requete)->valides()->sum('montant');

        return PaiementResource::collection(
            $requete->orderByDesc('date')->orderByDesc('id')->paginate($this->parPage($request, 20))
        )->additional(['meta' => ['total_encaisse' => $encaisse]]);
    }

    public function store(PaiementRequest $request, Inscription $inscription): JsonResponse
    {
        $paiement = $this->paiements->encaisser($inscription, $request->validated(), $request->user());

        return (new PaiementResource($paiement->load('inscription.eleve', 'inscription.classe', 'encaissePar')))
            ->response()
            ->setStatusCode(201);
    }

    public function annuler(Request $request, Paiement $paiement): PaiementResource
    {
        Gate::authorize('annuler', $paiement);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:255']], [
            'motif.required' => 'Indiquez le motif de l\'annulation.',
        ])['motif'];

        return new PaiementResource($this->paiements->annuler($paiement, $motif, $request->user())->load('inscription.eleve', 'inscription.classe', 'encaissePar'));
    }

    public function recu(Paiement $paiement): Response
    {
        Gate::authorize('view', $paiement);

        return response($this->recus->contenu($paiement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->recus->nomFichier($paiement).'"',
        ]);
    }
}
