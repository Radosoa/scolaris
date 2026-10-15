<?php

namespace App\Models;

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

    /**
     * Inscriptions en cours (hors transferts et abandons) : elles occupent une place.
     * Le modèle Inscription arrive en phase 3, on interroge donc la table directement.
     */
    public function effectif(): int
    {
        return $this->getConnection()->table('inscriptions')
            ->where('classe_id', $this->id)
            ->where('statut', 'inscrit')
            ->count();
    }

    #[Scope]
    protected function recherche(Builder $query, ?string $terme): void
    {
        $query->when($terme, fn (Builder $q) => $q->where('nom', 'like', "%{$terme}%"));
    }
}
