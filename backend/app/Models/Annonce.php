<?php

namespace App\Models;

use App\Enums\CibleAnnonce;
use App\Policies\AnnoncePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Annonce ciblée : tout l'établissement, les professeurs, les parents ou une classe. */
#[Table('annonces')]
#[Fillable(['titre', 'contenu', 'cible', 'classe_id', 'date_publication'])]
#[UsePolicy(AnnoncePolicy::class)]
class Annonce extends Model
{
    protected function casts(): array
    {
        return [
            'cible' => CibleAnnonce::class,
            'date_publication' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** @return BelongsTo<Classe, $this> */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function estPubliee(): bool
    {
        return $this->date_publication->isPast();
    }
}
