<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnes\CompteRequest;
use App\Http\Requests\Personnes\EleveRequest;
use App\Http\Requests\Personnes\PhotoRequest;
use App\Http\Resources\EleveResource;
use App\Models\Eleve;
use App\Services\CompteService;
use App\Services\EleveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EleveController extends Controller
{
    public function __construct(private readonly EleveService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Eleve::class);

        $eleves = $this->service->visiblesPar($request->user())
            ->recherche($request->string('q')->trim()->value())
            ->dansClasse($request->integer('classe_id') ?: null)
            ->with('inscriptionActive.classe')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate($this->parPage($request));

        return EleveResource::collection($eleves);
    }

    public function store(EleveRequest $request): JsonResponse
    {
        $eleve = $this->service->creer(
            $request->safe()->except('tuteurs'),
            $request->has('tuteurs') ? $request->validated('tuteurs') : null,
        );

        return (new EleveResource($this->charger($eleve)))->response()->setStatusCode(201);
    }

    public function show(Eleve $eleve): EleveResource
    {
        Gate::authorize('view', $eleve);

        return new EleveResource($this->charger($eleve));
    }

    public function update(EleveRequest $request, Eleve $eleve): EleveResource
    {
        $eleve = $this->service->modifier(
            $eleve,
            $request->safe()->except('tuteurs'),
            $request->has('tuteurs') ? $request->validated('tuteurs') : null,
        );

        return new EleveResource($this->charger($eleve));
    }

    public function destroy(Eleve $eleve): Response
    {
        Gate::authorize('delete', $eleve);

        $this->service->supprimer($eleve);

        return response()->noContent();
    }

    public function definirPhoto(PhotoRequest $request, Eleve $eleve): EleveResource
    {
        return new EleveResource($this->service->definirPhoto($eleve, $request->file('photo')));
    }

    public function supprimerPhoto(Eleve $eleve): EleveResource
    {
        Gate::authorize('update', $eleve);

        return new EleveResource($this->service->supprimerPhoto($eleve));
    }

    /** Servie via une URL signée (balise <img>), sans en-tête d'authentification. */
    public function photo(Eleve $eleve): StreamedResponse
    {
        abort_unless($eleve->photo_path, 404);

        return Storage::disk(EleveService::DISQUE_PHOTOS)->response($eleve->photo_path, headers: [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function creerCompte(CompteRequest $request, Eleve $eleve, CompteService $comptes): EleveResource
    {
        $comptes->creerPourEleve($eleve, $request->validated('email'), $request->validated('password'));

        return new EleveResource($this->charger($eleve->refresh()));
    }

    private function charger(Eleve $eleve): Eleve
    {
        return $eleve->load([
            'user',
            'tuteurs',
            'inscriptionActive.classe',
            'inscriptions' => fn ($q) => $q->with(['classe', 'anneeScolaire'])->latest('date_inscription'),
        ]);
    }
}
