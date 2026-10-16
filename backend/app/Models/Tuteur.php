<?php

namespace App\Models;

use App\Policies\TuteurPolicy;
use Database\Factories\TuteurFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Parent ou responsable légal d'un ou plusieurs élèves (« parent » est réservé en PHP). */
#[Table('tuteurs')]
#[Fillable(['nom', 'prenom', 'telephone', 'email'])]
#[UsePolicy(TuteurPolicy::class)]
class Tuteur extends Model
{
    /** @use HasFactory<TuteurFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Eleve, $this> */
    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'eleve_tuteur')
            ->withPivot('lien')
            ->using(EleveTuteur::class);
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
            ->orWhere('telephone', 'like', "%{$terme}%")
            ->orWhere('email', 'like', "%{$terme}%")
            ->orWhereRaw("CONCAT(prenom, ' ', nom) like ?", ["%{$terme}%"])));
    }
}
