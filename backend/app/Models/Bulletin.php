<?php

namespace App\Models;

use App\Policies\BulletinPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bulletin d'un élève (inscription) pour une période. Les moyennes sont stockées
 * avec 4 décimales et arrondies à l'affichage. Un bulletin validé est figé.
 */
#[Table('bulletins')]
#[Fillable([
    'inscription_id', 'periode_id', 'moyenne', 'rang', 'effectif', 'mention',
    'moyenne_classe', 'moyenne_max', 'moyenne_min',
    'heures_abs_justifiees', 'heures_abs_non_justifiees', 'nb_retards',
])]
#[UsePolicy(BulletinPolicy::class)]
class Bulletin extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['valide' => false];

    protected function casts(): array
    {
        return [
            'moyenne' => 'float',
            'rang' => 'integer',
            'effectif' => 'integer',
            'moyenne_classe' => 'float',
            'moyenne_max' => 'float',
            'moyenne_min' => 'float',
            'heures_abs_justifiees' => 'float',
            'heures_abs_non_justifiees' => 'float',
            'nb_retards' => 'integer',
            'valide' => 'boolean',
            'valide_le' => 'datetime',
            'calcule_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inscription, $this> */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /** @return BelongsTo<Periode, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /** @return HasMany<BulletinLigne, $this> */
    public function lignes(): HasMany
    {
        return $this->hasMany(BulletinLigne::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /** Un autre élève de la classe a-t-il le même rang ? */
    public function estExAequo(): bool
    {
        if ($this->rang === null) {
            return false;
        }

        return static::query()
            ->where('periode_id', $this->periode_id)
            ->where('rang', $this->rang)
            ->whereKeyNot($this->id)
            ->whereHas('inscription', fn (Builder $q) => $q->where('classe_id', $this->inscription->classe_id))
            ->exists();
    }

    /** Bulletins d'une classe pour une période. */
    #[Scope]
    protected function deClasse(Builder $query, Classe $classe, Periode $periode): void
    {
        $query->where('periode_id', $periode->id)
            ->whereHas('inscription', fn (Builder $q) => $q->where('classe_id', $classe->id));
    }
}
