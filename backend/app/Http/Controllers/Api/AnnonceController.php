<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnonceRequest;
use App\Http\Resources\AnnonceResource;
use App\Models\Annonce;
use App\Services\AnnonceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AnnonceController extends Controller
{
    public function __construct(private readonly AnnonceService $annonces) {}

    /** Annonces visibles par l'utilisateur, les plus récentes d'abord (?q= dans le titre ou le message). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $terme = $request->string('q')->trim()->value();

        return AnnonceResource::collection(
            $this->annonces->visiblesPar($request->user())
                ->when($terme, fn ($q) => $q->where(fn ($s) => $s->where('titre', 'like', "%{$terme}%")->orWhere('contenu', 'like', "%{$terme}%")))
                ->paginate($this->parPage($request, 10))
        );
    }

    /** Une annonce qui ne concerne pas l'utilisateur répond 404 (son existence n'est pas révélée). */
    public function show(Request $request, Annonce $annonce): AnnonceResource
    {
        if (! $this->annonces->estVisiblePar($annonce, $request->user())) {
            throw new NotFoundHttpException;
        }

        return new AnnonceResource($annonce->load('auteur', 'classe'));
    }

    public function store(AnnonceRequest $request): JsonResponse
    {
        $annonce = $this->annonces->creer($request->validated(), $request->user());

        return (new AnnonceResource($annonce->load('auteur', 'classe')))->response()->setStatusCode(201);
    }

    public function update(AnnonceRequest $request, Annonce $annonce): AnnonceResource
    {
        return new AnnonceResource($this->annonces->modifier($annonce, $request->validated())->load('auteur', 'classe'));
    }

    public function destroy(Annonce $annonce): Response
    {
        Gate::authorize('delete', $annonce);

        $annonce->delete();

        return response()->noContent();
    }
}
