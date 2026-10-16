<?php

namespace App\Services;

use App\Enums\CibleAnnonce;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Annonce;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Annonces ciblées. Un lecteur ne voit que les annonces publiées (date atteinte) qui le
 * concernent : tout l'établissement, son rôle (professeurs, parents) ou une de ses classes
 * (élèves de la classe, leurs parents, ses professeurs). Les rédacteurs voient tout.
 */
class AnnonceService
{
    /** @return Builder<Annonce> */
    public function visiblesPar(User $user): Builder
    {
        $requete = Annonce::query()->with(['auteur', 'classe'])->orderByDesc('date_publication')->orderByDesc('id');

        if ($user->can(Permission::AnnoncesPublier->value)) {
            return $requete;
        }

        $classes = $this->classesDe($user);

        return $requete
            ->where('date_publication', '<=', now())
            ->where(fn (Builder $q) => $q
                ->where('cible', CibleAnnonce::Tous)
                ->when($user->hasRole(Role::Professeur->value), fn (Builder $p) => $p->orWhere('cible', CibleAnnonce::Professeurs))
                ->when($user->hasRole(Role::Parent->value), fn (Builder $p) => $p->orWhere('cible', CibleAnnonce::Parents))
                ->when($classes !== [], fn (Builder $c) => $c->orWhere(fn (Builder $k) => $k
                    ->where('cible', CibleAnnonce::Classe)
                    ->whereIn('classe_id', $classes))));
    }

    public function estVisiblePar(Annonce $annonce, User $user): bool
    {
        return $this->visiblesPar($user)->whereKey($annonce->id)->exists();
    }

    /** @param  array{titre: string, contenu: string, cible: string, classe_id?: int|null, date_publication?: string|null}  $donnees */
    public function creer(array $donnees, User $auteur): Annonce
    {
        $annonce = new Annonce($this->normaliser($donnees));
        $annonce->auteur()->associate($auteur)->save();

        return $annonce;
    }

    /** @param  array{titre: string, contenu: string, cible: string, classe_id?: int|null, date_publication?: string|null}  $donnees */
    public function modifier(Annonce $annonce, array $donnees): Annonce
    {
        $annonce->update($this->normaliser($donnees));

        return $annonce;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function normaliser(array $donnees): array
    {
        return [
            ...$donnees,
            'classe_id' => $donnees['cible'] === CibleAnnonce::Classe->value ? $donnees['classe_id'] : null,
            'date_publication' => $donnees['date_publication'] ?? now(),
        ];
    }

    /** @return list<int> classes rattachées à l'utilisateur (année active) */
    private function classesDe(User $user): array
    {
        $ids = [];
        if ($user->hasRole(Role::Professeur->value) && $user->enseignant) {
            $ids = $user->enseignant->classeIds();
        }

        $eleveIds = [];
        if ($user->hasRole(Role::Eleve->value) && $user->eleve) {
            $eleveIds[] = $user->eleve->id;
        }
        if ($user->hasRole(Role::Parent->value) && $user->tuteur) {
            $eleveIds = [...$eleveIds, ...$user->tuteur->eleves()->pluck('eleves.id')->all()];
        }
        if ($eleveIds !== []) {
            $ids = [...$ids, ...Inscription::whereIn('eleve_id', $eleveIds)
                ->whereHas('anneeScolaire', fn (Builder $q) => $q->where('active', true))
                ->pluck('classe_id')->all()];
        }

        return array_values(array_unique($ids));
    }
}
