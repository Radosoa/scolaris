<?php

namespace App\Models;

use App\Policies\EnseignantPolicy;
use Database\Factories\EnseignantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('enseignants')]
#[Fillable(['nom', 'prenom', 'specialite', 'telephone'])]
#[UsePolicy(EnseignantPolicy::class)]
class Enseignant extends Model
{
    /** @use HasFactory<EnseignantFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Affectation, $this> */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    /** Identifiants des classes où l'enseignant a au moins une affectation. */
    public function classeIds(): array
    {
        return $this->affectations()->distinct()->pluck('classe_id')->all();
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when($terme, fn (Builder $q) => $q->where(fn (Builder $sous) => $sous
            ->where('nom', 'like', "%{$terme}%")
            ->orWhere('prenom', 'like', "%{$terme}%")
            ->orWhere('specialite', 'like', "%{$terme}%")
            ->orWhereRaw("CONCAT(prenom, ' ', nom) like ?", ["%{$terme}%"])));
    }
}
