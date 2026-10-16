<?php

namespace App\Models;

use App\Enums\Sexe;
use App\Enums\StatutInscription;
use App\Policies\ElevePolicy;
use Database\Factories\EleveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('eleves')]
#[Fillable(['nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance'])]
#[UsePolicy(ElevePolicy::class)]
class Eleve extends Model
{
    /** @use HasFactory<EleveFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'date_naissance' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Tuteur, $this> */
    public function tuteurs(): BelongsToMany
    {
        return $this->belongsToMany(Tuteur::class, 'eleve_tuteur')
            ->withPivot('lien')
            ->using(EleveTuteur::class);
    }

    /** @return HasMany<Inscription, $this> */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * Inscription de l'année active (quel que soit son statut), s'il y en a une.
     *
     * @return HasOne<Inscription, $this>
     */
    public function inscriptionActive(): HasOne
    {
        return $this->hasOne(Inscription::class)
            ->whereHas('anneeScolaire', fn (Builder $q) => $q->where('active', true));
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
            ->orWhere('matricule', 'like', "%{$terme}%")
            ->orWhereRaw("CONCAT(prenom, ' ', nom) like ?", ["%{$terme}%"])));
    }

    /** Élèves inscrits (statut « inscrit ») dans la classe donnée. */
    #[Scope]
    protected function dansClasse(Builder $query, ?int $classeId): void
    {
        $query->when($classeId, fn (Builder $q) => $q->whereHas('inscriptions', fn (Builder $i) => $i
            ->where('classe_id', $classeId)
            ->where('statut', StatutInscription::Inscrit)));
    }
}
