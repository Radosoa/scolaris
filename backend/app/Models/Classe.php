<?php

namespace App\Models;

use App\Enums\StatutInscription;
use App\Policies\StructurePolicy;
use Database\Factories\ClasseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('classes')]
#[Fillable(['niveau_id', 'annee_scolaire_id', 'nom', 'capacite'])]
#[UsePolicy(StructurePolicy::class)]
class Classe extends Model
{
    /** @use HasFactory<ClasseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['capacite' => 'integer'];
    }

    /** @return BelongsTo<Niveau, $this> */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /** @return HasMany<Inscription, $this> */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /** @return HasMany<Affectation, $this> */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    /** Inscriptions en cours (hors transferts et abandons) : elles occupent une place. */
    public function effectif(): int
    {
        return $this->inscriptions()->where('statut', StatutInscription::Inscrit)->count();
    }

    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when($terme, fn (Builder $q) => $q->where('nom', 'like', "%{$terme}%"));
    }
}
