<?php

namespace App\Services\VieScolaire;

use App\Enums\Role;
use App\Enums\StatutInscription;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Creneau;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Emploi du temps : créneaux hebdomadaires sans chevauchement pour un même professeur,
 * une même salle ou une même classe (sur l'année scolaire de la classe).
 */
class EmploiDuTempsService
{
    /**
     * @param  array{affectation_id: int, jour: int, heure_debut: string, heure_fin: string, salle?: string|null}  $donnees
     */
    public function creer(array $donnees): Creneau
    {
        $creneau = new Creneau($donnees);
        $this->refuserSiConflit($creneau);
        $creneau->save();

        return $creneau;
    }

    /**
     * @param  array{affectation_id: int, jour: int, heure_debut: string, heure_fin: string, salle?: string|null}  $donnees
     */
    public function modifier(Creneau $creneau, array $donnees): Creneau
    {
        $creneau->fill($donnees);
        $this->refuserSiConflit($creneau);
        $creneau->save();

        return $creneau;
    }

    public function supprimer(Creneau $creneau): void
    {
        $creneau->delete();
    }

    /**
     * Conflits du créneau avec ceux déjà enregistrés (lui-même exclu s'il existe déjà).
     *
     * @return list<string> messages lisibles, vide si aucun conflit
     */
    public function conflits(Creneau $creneau): array
    {
        $affectation = Affectation::with(['classe', 'enseignant'])->findOrFail($creneau->affectation_id);
        $salle = $creneau->salle === null ? null : mb_strtolower($creneau->salle);

        $chevauchants = Creneau::query()
            ->where('jour', $creneau->jour)
            ->where('heure_debut', '<', $creneau->heure_fin)
            ->where('heure_fin', '>', $creneau->heure_debut)
            ->when($creneau->exists, fn (Builder $q) => $q->whereKeyNot($creneau->id))
            ->whereHas('affectation.classe', fn (Builder $q) => $q->where('annee_scolaire_id', $affectation->classe->annee_scolaire_id))
            ->with(['affectation.classe', 'affectation.matiere', 'affectation.enseignant'])
            ->orderBy('heure_debut')
            ->get();

        $messages = [];
        foreach ($chevauchants as $autre) {
            $a = $autre->affectation;
            $horaire = $autre->libelleHoraire();

            if ($a->classe_id === $affectation->classe_id) {
                $messages[] = "Conflit de classe : la {$a->classe->nom} a déjà {$a->matiere->nom} avec {$a->enseignant->nomComplet()} le {$horaire}.";
            } elseif ($a->enseignant_id === $affectation->enseignant_id) {
                $messages[] = "Conflit de professeur : {$a->enseignant->nomComplet()} a déjà cours en {$a->classe->nom} ({$a->matiere->nom}) le {$horaire}.";
            }

            if ($salle !== null && $autre->salle !== null && mb_strtolower($autre->salle) === $salle) {
                $messages[] = "Conflit de salle : la salle {$autre->salle} est occupée par la {$a->classe->nom} ({$a->matiere->nom}) le {$horaire}.";
            }
        }

        return $messages;
    }

    /**
     * Emploi du temps visible par l'utilisateur, pour une classe ou un professeur.
     * Sans filtre : le sien (professeur) ou celui de sa classe (élève).
     *
     * @return array{type: 'classe'|'enseignant', id: int, libelle: string, creneaux: Collection<int, Creneau>}
     *
     * @throws AuthorizationException si l'utilisateur n'a pas accès à cet emploi du temps
     * @throws ValidationException si aucun emploi du temps n'est désigné
     */
    public function pour(User $user, ?int $classeId, ?int $enseignantId): array
    {
        if (! $classeId && ! $enseignantId) {
            [$classeId, $enseignantId] = $this->parDefaut($user);
        }

        if ($enseignantId) {
            $enseignant = Enseignant::withTrashed()->findOrFail($enseignantId);
            if (! $user->estAdministration() && $user->enseignant?->id !== $enseignant->id) {
                throw new AuthorizationException;
            }

            return [
                'type' => 'enseignant',
                'id' => $enseignant->id,
                'libelle' => $enseignant->nomComplet(),
                'creneaux' => $this->requete()
                    ->whereHas('affectation', fn (Builder $q) => $q->where('enseignant_id', $enseignant->id)
                        ->whereHas('classe', fn (Builder $c) => $c->where('annee_scolaire_id', AnneeScolaire::active()->value('id'))))
                    ->get(),
            ];
        }

        $classe = Classe::findOrFail($classeId);
        if (! $this->peutVoirClasse($user, $classe)) {
            throw new AuthorizationException;
        }

        return [
            'type' => 'classe',
            'id' => $classe->id,
            'libelle' => $classe->nom,
            'creneaux' => $this->requete()->whereHas('affectation', fn (Builder $q) => $q->where('classe_id', $classe->id))->get(),
        ];
    }

    /** @return Builder<Creneau> */
    private function requete(): Builder
    {
        return Creneau::query()
            ->with(['affectation.classe', 'affectation.matiere', 'affectation.enseignant'])
            ->orderBy('jour')
            ->orderBy('heure_debut');
    }

    /** @return array{0: int|null, 1: int|null} [classe_id, enseignant_id] */
    private function parDefaut(User $user): array
    {
        if ($user->hasRole(Role::Professeur->value) && $user->enseignant) {
            return [null, $user->enseignant->id];
        }

        if ($user->hasRole(Role::Eleve->value) && $user->eleve) {
            $classeId = $user->eleve->inscriptionActive?->statut === StatutInscription::Inscrit
                ? $user->eleve->inscriptionActive->classe_id
                : null;
            if ($classeId) {
                return [$classeId, null];
            }
        }

        throw ValidationException::withMessages(['classe_id' => 'Choisissez une classe ou un professeur.']);
    }

    private function peutVoirClasse(User $user, Classe $classe): bool
    {
        return match (true) {
            $user->estAdministration() => true,
            $user->hasRole(Role::Professeur->value) => $user->enseignant !== null
                && in_array($classe->id, $user->enseignant->classeIds()),
            $user->hasRole(Role::Eleve->value) => $user->eleve !== null
                && $user->eleve->inscriptions()->where('classe_id', $classe->id)->exists(),
            $user->hasRole(Role::Parent->value) => $user->tuteur !== null
                && $user->tuteur->eleves()->whereHas('inscriptions', fn (Builder $q) => $q->where('classe_id', $classe->id))->exists(),
            default => false,
        };
    }

    /** @throws ValidationException */
    private function refuserSiConflit(Creneau $creneau): void
    {
        $conflits = $this->conflits($creneau);
        if ($conflits) {
            throw ValidationException::withMessages(['conflits' => $conflits]);
        }
    }
}
