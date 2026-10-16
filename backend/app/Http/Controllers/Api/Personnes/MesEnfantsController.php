<?php

namespace App\Http\Controllers\Api\Personnes;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\EleveResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Espace parent : uniquement les enfants rattachés au compte connecté. */
class MesEnfantsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user->hasRole(Role::Parent->value), 403);

        $enfants = $user->tuteur
            ? $user->tuteur->eleves()->with('inscriptionActive.classe')->orderBy('prenom')->get()
            : collect();

        return EleveResource::collection($enfants);
    }
}
