<?php

namespace App\Http\Controllers\Api\Bulletins;

use App\Http\Controllers\Controller;
use App\Http\Resources\BulletinResource;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Periode;
use App\Services\Bulletins\BulletinPdfService;
use App\Services\Bulletins\BulletinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Écran de la direction : bulletins d'une classe pour une période
 * (calcul → prévisualisation → validation → PDF).
 */
class BulletinClasseController extends Controller
{
    public function __construct(
        private readonly BulletinService $bulletins,
        private readonly BulletinPdfService $pdf,
    ) {}

    /**
     * Bulletins de la classe, classés par rang (une classe tient sur une page :
     * pas de pagination). L'état du traitement est renvoyé dans `meta`.
     */
    public function index(Classe $classe, Periode $periode): AnonymousResourceCollection
    {
        Gate::authorize('gerer', Bulletin::class);

        $bulletins = Bulletin::deClasse($classe, $periode)
            ->with('inscription.eleve')
            ->get()
            ->sortBy(fn (Bulletin $b) => [$b->rang ?? PHP_INT_MAX, $b->inscription->eleve->nom, $b->inscription->eleve->prenom])
            ->values();

        return BulletinResource::collection($bulletins)->additional(['meta' => [
            'classe' => ['id' => $classe->id, 'nom' => $classe->nom],
            'periode' => ['id' => $periode->id, 'libelle' => $periode->libelle, 'cloturee' => $periode->cloturee],
            ...$this->bulletins->etat($classe, $periode),
            ...$this->pdf->etatClasse($classe, $periode),
        ]]);
    }

    public function calculer(Classe $classe, Periode $periode): AnonymousResourceCollection
    {
        Gate::authorize('gerer', Bulletin::class);

        $this->bulletins->calculer($classe, $periode);

        return $this->index($classe, $periode);
    }

    public function valider(Request $request, Classe $classe, Periode $periode): AnonymousResourceCollection
    {
        Gate::authorize('valider', Bulletin::class);

        $this->bulletins->valider($classe, $periode, $request->user());

        return $this->index($classe, $periode);
    }

    /** Lance la génération en lot (job de queue) : 202, l'avancement se lit dans `meta`. */
    public function genererPdf(Classe $classe, Periode $periode): JsonResponse
    {
        Gate::authorize('gerer', Bulletin::class);

        $this->pdf->lancerGenerationClasse($classe, $periode);

        return response()->json(['message' => 'Génération des PDF lancée.'], 202);
    }

    public function telechargerPdf(Classe $classe, Periode $periode): StreamedResponse|JsonResponse
    {
        Gate::authorize('gerer', Bulletin::class);

        $chemin = $this->pdf->cheminClasse($classe, $periode);
        if (! Storage::exists($chemin)) {
            return response()->json(['message' => 'Le PDF de la classe n\'a pas encore été généré.'], 404);
        }

        return Storage::download($chemin, $this->pdf->nomFichierClasse($classe, $periode));
    }
}
