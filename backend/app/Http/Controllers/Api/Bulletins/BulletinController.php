<?php

namespace App\Http\Controllers\Api\Bulletins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bulletins\AppreciationGeneraleRequest;
use App\Http\Resources\BulletinResource;
use App\Models\Bulletin;
use App\Models\Eleve;
use App\Services\Bulletins\BulletinPdfService;
use App\Services\Bulletins\BulletinService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulletinController extends Controller
{
    public function __construct(
        private readonly BulletinService $bulletins,
        private readonly BulletinPdfService $pdf,
    ) {}

    /** Bulletins d'un élève : validés uniquement, sauf pour la direction qui voit aussi les brouillons. */
    public function deLEleve(Request $request, Eleve $eleve): AnonymousResourceCollection
    {
        Gate::authorize('view', $eleve);

        $bulletins = Bulletin::query()
            ->whereHas('inscription', fn (Builder $q) => $q->where('eleve_id', $eleve->id))
            ->unless($request->user()->can('gerer', Bulletin::class), fn (Builder $q) => $q->where('valide', true))
            ->with(['periode.anneeScolaire', 'inscription.eleve', 'inscription.classe'])
            ->join('periodes', 'periodes.id', '=', 'bulletins.periode_id')
            ->orderByDesc('periodes.date_debut')
            ->select('bulletins.*')
            ->paginate($this->parPage($request, 20));

        return BulletinResource::collection($bulletins);
    }

    public function show(Bulletin $bulletin): BulletinResource
    {
        Gate::authorize('view', $bulletin);

        return new BulletinResource($this->charger($bulletin));
    }

    public function appreciation(AppreciationGeneraleRequest $request, Bulletin $bulletin): BulletinResource
    {
        $this->bulletins->definirAppreciationGenerale($bulletin, $request->validated('appreciation_generale'));

        return new BulletinResource($this->charger($bulletin));
    }

    /** PDF individuel : fichier stocké pour un bulletin validé, rendu à la volée (« Projet ») sinon. */
    public function pdf(Bulletin $bulletin): Response|StreamedResponse
    {
        Gate::authorize('view', $bulletin);

        $bulletin->load(['inscription.eleve', 'periode']);
        $nom = $this->pdf->nomFichier($bulletin);

        if ($bulletin->valide) {
            return Storage::download($this->pdf->fichier($bulletin), $nom);
        }

        return response($this->pdf->contenu($bulletin), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nom}\"",
        ]);
    }

    private function charger(Bulletin $bulletin): Bulletin
    {
        return $bulletin->load([
            'lignes.matiere',
            'periode.anneeScolaire',
            'inscription.eleve',
            'inscription.classe.affectations.enseignant',
        ]);
    }
}
