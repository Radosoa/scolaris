<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Niveau
 */
class NiveauResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'classes_count' => $this->whenCounted('classes'),
            'coefficients_count' => $this->whenCounted('coefficients'),
        ];
    }
}
