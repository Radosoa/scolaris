<?php

namespace App\Http\Resources;

use App\Models\BulletinLigne;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bulletin d'un élève. Les moyennes sont arrondies ici, pour l'affichage
 * (config bulletin.decimales_affichage) ; les rangs ont été calculés sur les valeurs exactes.
 *
 * @mixin \App\Models\Bulletin
 */
class BulletinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inscription = $this->whenLoaded('inscription');

        return [
            'id' => $this->id,
            'periode_id' => $this->periode_id,
            'periode' => $this->whenLoaded('periode', fn () => [
                'id' => $this->periode->id,
                'libelle' => $this->periode->libelle,
                'annee' => $this->periode->relationLoaded('anneeScolaire') ? $this->periode->anneeScolaire->libelle : null,
            ]),
            'eleve' => $this->whenLoaded('inscription', fn () => [
                'id' => $inscription->eleve->id,
                'matricule' => $inscription->eleve->matricule,
                'nom' => $inscription->eleve->nom,
                'prenom' => $inscription->eleve->prenom,
            ]),
            'classe' => $this->when(
                $this->relationLoaded('inscription') && $inscription->relationLoaded('classe'),
                fn () => ['id' => $inscription->classe->id, 'nom' => $inscription->classe->nom],
            ),
            'moyenne' => self::arrondi($this->moyenne),
            'rang' => $this->rang,
            'ex_aequo' => $this->when($this->relationLoaded('lignes'), fn () => $this->estExAequo()),
            'effectif' => $this->effectif,
            'mention' => $this->mention,
            'appreciation_generale' => $this->appreciation_generale,
            'moyenne_classe' => self::arrondi($this->moyenne_classe),
            'moyenne_max' => self::arrondi($this->moyenne_max),
            'moyenne_min' => self::arrondi($this->moyenne_min),
            'absences' => [
                'heures_justifiees' => $this->heures_abs_justifiees,
                'heures_non_justifiees' => $this->heures_abs_non_justifiees,
                'retards' => $this->nb_retards,
            ],
            'valide' => $this->valide,
            'valide_le' => $this->valide_le?->toIso8601String(),
            'calcule_le' => $this->calcule_le?->toIso8601String(),
            'lignes' => $this->whenLoaded('lignes', fn () => $this->lignes
                ->sortBy('matiere.nom')
                ->values()
                ->map(fn (BulletinLigne $ligne) => $this->ligne($ligne))),
        ];
    }

    /** @return array<string, mixed> */
    private function ligne(BulletinLigne $ligne): array
    {
        $classe = $this->relationLoaded('inscription') && $this->inscription->relationLoaded('classe') ? $this->inscription->classe : null;
        $affectation = $classe?->relationLoaded('affectations') ? $classe->affectations->firstWhere('matiere_id', $ligne->matiere_id) : null;

        return [
            'id' => $ligne->id,
            'matiere' => ['id' => $ligne->matiere->id, 'nom' => $ligne->matiere->nom, 'code' => $ligne->matiere->code],
            'enseignant' => $affectation?->enseignant?->nomComplet(),
            'coefficient' => $ligne->coefficient,
            'moyenne' => self::arrondi($ligne->moyenne),
            'rang' => $ligne->rang_matiere,
            'moyenne_classe' => self::arrondi($ligne->moyenne_classe),
            'moyenne_max' => self::arrondi($ligne->moyenne_max),
            'moyenne_min' => self::arrondi($ligne->moyenne_min),
            'appreciation' => $ligne->appreciation,
        ];
    }

    public static function arrondi(?float $valeur): ?float
    {
        return $valeur === null ? null : round($valeur, (int) config('bulletin.decimales_affichage'));
    }
}
