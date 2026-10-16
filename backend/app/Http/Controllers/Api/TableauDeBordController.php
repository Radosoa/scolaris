<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Services\TableauDeBordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tableau de bord de l'utilisateur connecté, selon son rôle principal. */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, TableauDeBordService $tableaux): JsonResponse
    {
        $user = $request->user();

        [$type, $donnees] = match (true) {
            $user->hasRole(Role::Admin->value) => ['direction', fn () => $tableaux->direction()],
            $user->hasRole(Role::Secretariat->value) => ['secretariat', fn () => $tableaux->secretariat($user)],
            $user->hasRole(Role::Professeur->value) => ['professeur', fn () => $tableaux->professeur($user)],
            $user->hasRole(Role::Parent->value) => ['parent', fn () => $tableaux->parent($user)],
            $user->hasRole(Role::Eleve->value) => ['eleve', fn () => $tableaux->eleve($user)],
            default => [null, fn () => []],
        };

        abort_if($type === null, 403);

        return response()->json(['data' => ['type' => $type, ...$donnees()]]);
    }
}
