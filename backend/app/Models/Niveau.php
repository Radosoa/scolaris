<?php

namespace App\Models;

use App\Policies\StructurePolicy;
use Database\Factories\NiveauFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('niveaux')]
#[Fillable(['libelle', 'ordre'])]
#[UsePolicy(StructurePolicy::class)]
class Niveau extends Model
{
    /** @use HasFactory<NiveauFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['ordre' => 'integer'];
    }

    /** @return HasMany<Classe, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    /** @return HasMany<Coefficient, $this> */
    public function coefficients(): HasMany
    {
        return $this->hasMany(Coefficient::class);
    }

    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when($terme, fn (Builder $q) => $q->where('libelle', 'like', "%{$terme}%"));
    }
}
