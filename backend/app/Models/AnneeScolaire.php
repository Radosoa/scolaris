<?php

namespace App\Models;

use App\Policies\StructurePolicy;
use Database\Factories\AnneeScolaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('annees_scolaires')]
#[Fillable(['libelle', 'date_debut', 'date_fin'])]
#[UsePolicy(StructurePolicy::class)]
class AnneeScolaire extends Model
{
    /** @use HasFactory<AnneeScolaireFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['active' => false];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<Periode, $this> */
    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('ordre');
    }

    /** @return HasMany<Classe, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when($terme, fn (Builder $q) => $q->where('libelle', 'like', "%{$terme}%"));
    }
}
