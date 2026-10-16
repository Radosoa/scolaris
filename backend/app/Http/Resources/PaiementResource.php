<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Paiement
 */
class PaiementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inscription = $this->whenLoaded('inscription');

        return [
            'id' => $this->id,
            'numero_recu' => $this->numero_recu,
            'montant' => (float) $this->montant,
            'date' => $this->date->toDateString(),
            'mode' => $this->mode->value,
            'mode_libelle' => $this->mode->libelle(),
            'reference' => $this->reference,
            'encaisse_par' => $this->whenLoaded('encaissePar', fn () => $this->encaissePar?->name),
            'annule' => $this->estAnnule(),
            'annule_le' => $this->annule_le?->toIso8601String(),
            'motif_annulation' => $this->motif_annulation,
            'inscription_id' => $this->inscription_id,
            'eleve' => $this->when(
                $this->relationLoaded('inscription') && $inscription->relationLoaded('eleve'),
                fn () => ['id' => $inscription->eleve->id, 'matricule' => $inscription->eleve->matricule, 'nom' => $inscription->eleve->nom, 'prenom' => $inscription->eleve->prenom],
            ),
            'classe' => $this->when(
                $this->relationLoaded('inscription') && $inscription->relationLoaded('classe'),
                fn () => ['id' => $inscription->classe->id, 'nom' => $inscription->classe->nom],
            ),
        ];
    }
}
