<?php

namespace App\Models;

use App\Policies\CreneauPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Heure de cours hebdomadaire : une affectation (professeur + matière + classe),
 * un jour (1 = lundi … 6 = samedi), une plage horaire et une salle.
 */
#[Table('creneaux')]
#[Fillable(['affectation_id', 'jour', 'heure_debut', 'heure_fin', 'salle'])]
#[UsePolicy(CreneauPolicy::class)]
class Creneau extends Model
{
    public const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];

    protected function casts(): array
    {
        return ['jour' => 'integer'];
    }

    /** Heures toujours exposées au format « HH:MM » (MySQL renvoie « HH:MM:SS »). */
    protected function heureDebut(): Attribute
    {
        return Attribute::get(fn (?string $valeur) => $valeur === null ? null : substr($valeur, 0, 5));
    }

    protected function heureFin(): Attribute
    {
        return Attribute::get(fn (?string $valeur) => $valeur === null ? null : substr($valeur, 0, 5));
    }

    /** Salle normalisée (espaces superflus retirés, vide = aucune salle). */
    protected function salle(): Attribute
    {
        return Attribute::set(function (?string $valeur) {
            $valeur = $valeur === null ? null : preg_replace('/\s+/', ' ', trim($valeur));

            return $valeur === '' ? null : $valeur;
        });
    }

    /** @return BelongsTo<Affectation, $this> */
    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    /** @return HasMany<Appel, $this> */
    public function appels(): HasMany
    {
        return $this->hasMany(Appel::class);
    }

    public function libelleJour(): string
    {
        return self::JOURS[$this->jour] ?? '?';
    }

    /** Ex. « lundi de 08:00 à 09:00 ». */
    public function libelleHoraire(): string
    {
        return mb_strtolower($this->libelleJour())." de {$this->heure_debut} à {$this->heure_fin}";
    }
}
