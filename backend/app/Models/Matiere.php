<?php

namespace App\Models;

use App\Policies\StructurePolicy;
use Database\Factories\MatiereFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('matieres')]
#[Fillable(['nom', 'code'])]
#[UsePolicy(StructurePolicy::class)]
class Matiere extends Model
{
    /** @use HasFactory<MatiereFactory> */
    use HasFactory;

    /** Les codes sont toujours stockés en majuscules (ex. MATH, FR, HG). */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string $valeur) => mb_strtoupper(trim($valeur)));
    }

    /** @return HasMany<Coefficient, $this> */
    public function coefficients(): HasMany
    {
        return $this->hasMany(Coefficient::class);
    }

    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when($terme, fn (Builder $q) => $q->where(
            fn (Builder $sous) => $sous->where('nom', 'like', "%{$terme}%")->orWhere('code', 'like', "%{$terme}%")
        ));
    }
}
