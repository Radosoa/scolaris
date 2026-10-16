<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'actif' => $this->actif,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'derniere_connexion_at' => $this->derniere_connexion_at?->toIso8601String(),
            // Profils rattachés au compte (null si absent) : utilisés par les espaces élève / parent / professeur
            'profil' => [
                'eleve_id' => $this->eleve?->id,
                'tuteur_id' => $this->tuteur?->id,
                'enseignant_id' => $this->enseignant?->id,
            ],
        ];
    }
}
