<?php

namespace App\Http\Controllers\Api\Notes;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\Periode;
use App\Services\ReleveNotesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Notes d'un élève sur une période. Mêmes droits que la fiche élève :
 * l'élève lui-même, ses parents, ses professeurs et l'administration.
 */
class ReleveNotesController extends Controller
{
    public function __invoke(Request $request, Eleve $eleve, ReleveNotesService $releves): JsonResponse
    {
        Gate::authorize('view', $eleve);

        $periodes = Periode::query()
            ->whereIn('annee_scolaire_id', $eleve->inscriptions()->pluck('annee_scolaire_id'))
            ->orderByDesc('date_debut')
            ->get();

        // Par défaut : la période en cours, sinon la plus récente
        $periode = $request->integer('periode_id')
            ? $periodes->firstWhere('id', $request->integer('periode_id'))
            : ($periodes->first(fn (Periode $p) => now()->betweenIncluded($p->date_debut, $p->date_fin)) ?? $periodes->first());

        return response()->json(['data' => [
            'periodes' => $periodes->sortBy('date_debut')->values()->map(fn (Periode $p) => [
                'id' => $p->id, 'libelle' => $p->libelle, 'cloturee' => $p->cloturee,
            ]),
            'periode_id' => $periode?->id,
            'matieres' => $periode ? $releves->pour($eleve, $periode) : [],
        ]]);
    }
}
