<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Creneau
 */
class CreneauResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $affectation = $this->whenLoaded('affectation');

        return [
            'id' => $this->id,
            'affectation_id' => $this->affectation_id,
            'jour' => $this->jour,
            'jour_libelle' => $this->libelleJour(),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'salle' => $this->salle,
            'classe' => $this->whenLoaded('affectation', fn () => ['id' => $affectation->classe->id, 'nom' => $affectation->classe->nom]),
            'matiere' => $this->whenLoaded('affectation', fn () => [
                'id' => $affectation->matiere->id, 'nom' => $affectation->matiere->nom, 'code' => $affectation->matiere->code,
            ]),
            'enseignant' => $this->whenLoaded('affectation', fn () => [
                'id' => $affectation->enseignant->id, 'nom_complet' => $affectation->enseignant->nomComplet(),
            ]),
        ];
    }
}
