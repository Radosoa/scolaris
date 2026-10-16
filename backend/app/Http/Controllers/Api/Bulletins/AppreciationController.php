<?php

namespace App\Http\Controllers\Api\Bulletins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bulletins\AppreciationsRequest;
use App\Http\Resources\BulletinResource;
use App\Models\Affectation;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Periode;
use App\Services\Bulletins\BulletinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Appréciations du professeur sur sa matière, pour les bulletins d'une classe. */
class AppreciationController extends Controller
{
    public function __construct(private readonly BulletinService $bulletins) {}

    public function index(Request $request, Affectation $affectation): JsonResponse
    {
        Gate::authorize('saisirAppreciations', [Bulletin::class, $affectation]);

        $request->validate(['periode_id' => ['required', 'integer', 'exists:periodes,id']], [
            'periode_id.required' => 'Choisissez une période.',
        ]);

        return $this->reponse($affectation, Periode::findOrFail($request->integer('periode_id')));
    }

    public function enregistrer(AppreciationsRequest $request, Affectation $affectation): JsonResponse
    {
        $periode = Periode::findOrFail($request->validated('periode_id'));

        $this->bulletins->enregistrerAppreciations($affectation, $periode, $request->validated('appreciations'));

        return $this->reponse($affectation, $periode);
    }

    private function reponse(Affectation $affectation, Periode $periode): JsonResponse
    {
        $lignes = $this->bulletins->lignesDeMatiere($affectation->load('classe', 'matiere'), $periode);

        return response()->json(['data' => [
            'classe' => ['id' => $affectation->classe->id, 'nom' => $affectation->classe->nom],
            'matiere' => ['id' => $affectation->matiere->id, 'nom' => $affectation->matiere->nom],
            'periode' => ['id' => $periode->id, 'libelle' => $periode->libelle, 'cloturee' => $periode->cloturee],
            'calcule' => $lignes->isNotEmpty(),
            'verrouille' => (bool) $lignes->first()?->bulletin->valide,
            'lignes' => $lignes->map(fn (BulletinLigne $ligne) => [
                'id' => $ligne->id,
                'eleve' => [
                    'id' => $ligne->bulletin->inscription->eleve->id,
                    'nom' => $ligne->bulletin->inscription->eleve->nom,
                    'prenom' => $ligne->bulletin->inscription->eleve->prenom,
                ],
                'moyenne' => BulletinResource::arrondi($ligne->moyenne),
                'rang' => $ligne->rang_matiere,
                'appreciation' => $ligne->appreciation,
            ]),
        ]]);
    }
}
