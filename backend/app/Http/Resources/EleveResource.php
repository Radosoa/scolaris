<?php

namespace App\Http\Resources;

use App\Models\Eleve;
use App\Support\EcoleCourante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Eleve
 */
class EleveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => $this->nomComplet(),
            'sexe' => $this->sexe->value,
            'date_naissance' => $this->date_naissance->toDateString(),
            'lieu_naissance' => $this->lieu_naissance,
            // URL signée et temporaire : la photo d'un mineur n'est jamais publique
            'photo_url' => $this->photo_path
                ? URL::temporarySignedRoute('eleves.photo', now()->addHour(), array_filter(['eleve' => $this->id, 'v' => md5($this->photo_path), 'ecole' => app(EcoleCourante::class)->get()?->code]))
                : null,
            'compte' => $this->whenLoaded('user', fn () => $this->user ? ['email' => $this->user->email, 'actif' => $this->user->actif] : null),
            'tuteurs' => $this->whenLoaded('tuteurs', fn () => $this->tuteurs->map(fn ($tuteur) => [
                'id' => $tuteur->id,
                'nom_complet' => $tuteur->nomComplet(),
                'telephone' => $tuteur->telephone,
                'email' => $tuteur->email,
                'lien' => $tuteur->pivot->lien->value,
                'lien_libelle' => $tuteur->pivot->lien->libelle(),
            ])),
            'inscription_active' => $this->whenLoaded('inscriptionActive', fn () => $this->inscriptionActive
                ? new InscriptionResource($this->inscriptionActive)
                : null),
            'inscriptions' => InscriptionResource::collection($this->whenLoaded('inscriptions')),
            'archive' => $this->trashed(),
        ];
    }
}
