<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatutInscription;
use App\Exceptions\RegleMetierException;
use App\Models\Eleve;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EleveService
{
    /** Disque privé : les photos d'élèves (mineurs) ne sont jamais publiques. */
    public const DISQUE_PHOTOS = 'local';

    public function __construct(private readonly MatriculeService $matricules) {}

    /**
     * Élèves que l'utilisateur a le droit de lister.
     *
     * @return Builder<Eleve>
     */
    public function visiblesPar(User $user): Builder
    {
        $requete = Eleve::query();

        if ($user->estAdministration()) {
            return $requete;
        }

        if ($user->hasRole(Role::Professeur->value) && $user->enseignant) {
            $classes = $user->enseignant->classeIds();

            return $requete->whereHas('inscriptions', fn (Builder $q) => $q->whereIn('classe_id', $classes));
        }

        return $requete->whereRaw('1 = 0');
    }

    /**
     * @param  array{nom: string, prenom: string, sexe: string, date_naissance: string, lieu_naissance?: string|null}  $donnees
     * @param  list<array{tuteur_id: int, lien: string}>|null  $tuteurs
     */
    public function creer(array $donnees, ?array $tuteurs = null): Eleve
    {
        return DB::transaction(function () use ($donnees, $tuteurs) {
            $eleve = new Eleve($donnees);
            $eleve->matricule = $this->matricules->prochain();
            $eleve->save();

            if ($tuteurs !== null) {
                $this->synchroniserTuteurs($eleve, $tuteurs);
            }

            return $eleve;
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @param  list<array{tuteur_id: int, lien: string}>|null  $tuteurs  null = liens inchangés
     */
    public function modifier(Eleve $eleve, array $donnees, ?array $tuteurs = null): Eleve
    {
        return DB::transaction(function () use ($eleve, $donnees, $tuteurs) {
            $eleve->update($donnees);

            if ($tuteurs !== null) {
                $this->synchroniserTuteurs($eleve, $tuteurs);
            }

            return $eleve;
        });
    }

    /**
     * @param  list<array{tuteur_id: int, lien: string}>  $tuteurs
     */
    public function synchroniserTuteurs(Eleve $eleve, array $tuteurs): void
    {
        $eleve->tuteurs()->sync(
            collect($tuteurs)->mapWithKeys(fn (array $t) => [$t['tuteur_id'] => ['lien' => $t['lien']]])->all()
        );
    }

    public function definirPhoto(Eleve $eleve, UploadedFile $fichier): Eleve
    {
        $ancienne = $eleve->photo_path;
        $eleve->photo_path = $fichier->store('eleves/photos', self::DISQUE_PHOTOS);
        $eleve->save();

        if ($ancienne) {
            Storage::disk(self::DISQUE_PHOTOS)->delete($ancienne);
        }

        return $eleve;
    }

    public function supprimerPhoto(Eleve $eleve): Eleve
    {
        if ($eleve->photo_path) {
            Storage::disk(self::DISQUE_PHOTOS)->delete($eleve->photo_path);
            $eleve->photo_path = null;
            $eleve->save();
        }

        return $eleve;
    }

    /**
     * Archive le dossier (suppression logique : notes, bulletins et paiements sont conservés).
     */
    public function supprimer(Eleve $eleve): void
    {
        $inscrit = $eleve->inscriptions()
            ->where('statut', StatutInscription::Inscrit)
            ->whereHas('anneeScolaire', fn (Builder $q) => $q->where('active', true))
            ->exists();

        if ($inscrit) {
            throw new RegleMetierException(
                'Cet élève est inscrit pour l\'année en cours. Passez son inscription en « abandon » ou « transféré » avant d\'archiver son dossier.'
            );
        }

        DB::transaction(function () use ($eleve) {
            $eleve->user?->forceFill(['actif' => false])->save();
            $eleve->user?->tokens()->delete();
            $eleve->delete();
        });
    }
}
