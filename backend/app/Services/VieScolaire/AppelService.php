<?php

namespace App\Services\VieScolaire;

use App\Enums\StatutInscription;
use App\Enums\TypeAbsence;
use App\Models\Absence;
use App\Models\Appel;
use App\Models\Creneau;
use App\Models\Eleve;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Appel numérique : pour un créneau et une date, chaque élève de la classe est présent,
 * absent (toute la durée du créneau) ou en retard (de N minutes).
 */
class AppelService
{
    /**
     * Créneaux d'un professeur pour une date, avec l'état de l'appel.
     *
     * @return Collection<int, array{creneau: Creneau, appel: Appel|null}>
     */
    public function creneauxDuJour(User $user, CarbonImmutable $date): Collection
    {
        $creneaux = Creneau::query()
            ->where('jour', $date->dayOfWeekIso)
            ->whereHas('affectation', fn ($q) => $q
                ->when(! $user->hasRole('admin'), fn ($a) => $a->where('enseignant_id', $user->enseignant?->id ?? 0))
                ->whereHas('classe.anneeScolaire', fn ($an) => $an
                    ->where('date_debut', '<=', $date->toDateString())
                    ->where('date_fin', '>=', $date->toDateString())))
            ->with(['affectation.classe', 'affectation.matiere', 'affectation.enseignant'])
            ->orderBy('heure_debut')
            ->get();

        $appels = Appel::whereIn('creneau_id', $creneaux->pluck('id'))->whereDate('date', $date)->get()->keyBy('creneau_id');

        return $creneaux->map(fn (Creneau $c) => ['creneau' => $c, 'appel' => $appels->get($c->id)]);
    }

    /**
     * Feuille d'appel : élèves inscrits dans la classe avec leur statut déjà saisi.
     *
     * @return array{appel: Appel|null, eleves: Collection<int, array<string, mixed>>}
     */
    public function feuille(Creneau $creneau, CarbonImmutable $date): array
    {
        $this->verifierDate($creneau, $date);

        $absences = Absence::where('creneau_id', $creneau->id)->whereDate('date', $date)->get()->keyBy('eleve_id');

        $eleves = $this->elevesDeLaClasse($creneau)->map(function (Eleve $eleve) use ($absences, $creneau) {
            $absence = $absences->get($eleve->id);

            return [
                'eleve_id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'statut' => match ($absence?->type) {
                    TypeAbsence::Absence => 'absent',
                    TypeAbsence::Retard => 'retard',
                    null => 'present',
                },
                'minutes_retard' => $absence?->type === TypeAbsence::Retard
                    ? Absence::minutesEntre($creneau->heure_debut, $absence->heure_fin)
                    : null,
                'justifiee' => (bool) $absence?->justifiee,
            ];
        });

        return [
            'appel' => Appel::with('faitPar')->where('creneau_id', $creneau->id)->whereDate('date', $date)->first(),
            'eleves' => $eleves,
        ];
    }

    /**
     * Enregistre l'appel en une transaction. Les élèves non transmis sont considérés présents.
     * Une justification déjà saisie par la vie scolaire est conservée si le statut ne change pas.
     *
     * @param  list<array{eleve_id: int, statut: 'present'|'absent'|'retard', minutes_retard?: int|null}>  $lignes
     */
    public function enregistrer(Creneau $creneau, CarbonImmutable $date, array $lignes, User $par): Appel
    {
        $this->verifierDate($creneau, $date);

        $eleveIds = $this->elevesDeLaClasse($creneau)->pluck('id')->flip();
        $erreurs = [];
        foreach ($lignes as $index => $ligne) {
            if (! $eleveIds->has($ligne['eleve_id'])) {
                $erreurs["eleves.{$index}.eleve_id"] = 'Cet élève n\'est pas inscrit dans la classe.';
            } elseif ($ligne['statut'] === 'retard' && Absence::minutesEntre($creneau->heure_debut, $creneau->heure_fin) < ($ligne['minutes_retard'] ?? 0)) {
                $erreurs["eleves.{$index}.minutes_retard"] = 'Le retard dépasse la durée du cours : marquez l\'élève absent.';
            }
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        $statuts = collect($lignes)->keyBy('eleve_id');

        return DB::transaction(function () use ($creneau, $date, $statuts, $eleveIds, $par) {
            $existantes = Absence::where('creneau_id', $creneau->id)->whereDate('date', $date)->get()->keyBy('eleve_id');

            foreach ($eleveIds->keys() as $eleveId) {
                $ligne = $statuts->get($eleveId, ['statut' => 'present']);
                $existante = $existantes->get($eleveId);

                if ($ligne['statut'] === 'present') {
                    $existante?->delete();

                    continue;
                }

                $type = $ligne['statut'] === 'retard' ? TypeAbsence::Retard : TypeAbsence::Absence;
                $absence = $existante ?? new Absence(['eleve_id' => $eleveId, 'creneau_id' => $creneau->id, 'date' => $date->toDateString()]);
                if ($absence->exists && $absence->type !== $type) {
                    // Un retard devenu absence (ou l'inverse) doit être justifié à nouveau
                    $absence->fill(['justifiee' => false, 'motif' => null]);
                }
                $absence->fill([
                    'type' => $type,
                    'heure_debut' => $creneau->heure_debut,
                    'heure_fin' => $type === TypeAbsence::Retard
                        ? $this->ajouterMinutes($creneau->heure_debut, (int) $ligne['minutes_retard'])
                        : $creneau->heure_fin,
                ]);
                $absence->forceFill(['saisie_par' => $par->id])->save();
            }

            $appel = Appel::firstOrNew(['creneau_id' => $creneau->id, 'date' => $date->toDateString()]);
            $appel->fait_par = $par->id;
            $appel->touch();

            return $appel;
        });
    }

    /**
     * La date doit tomber le jour du créneau, dans l'année scolaire de la classe, et ne pas être future.
     *
     * @throws ValidationException
     */
    private function verifierDate(Creneau $creneau, CarbonImmutable $date): void
    {
        $annee = $creneau->affectation->classe->anneeScolaire;

        $erreur = match (true) {
            $date->dayOfWeekIso !== $creneau->jour => "Ce cours a lieu le {$creneau->libelleJour()} : choisissez un {$creneau->libelleJour()}.",
            $date->isAfter(today()) => 'On ne fait pas l\'appel d\'un cours à venir.',
            $date->lt($annee->date_debut) || $date->gt($annee->date_fin) => "La date doit appartenir à l'année scolaire {$annee->libelle}.",
            default => null,
        };

        if ($erreur) {
            throw ValidationException::withMessages(['date' => $erreur]);
        }
    }

    /** @return Collection<int, Eleve> */
    private function elevesDeLaClasse(Creneau $creneau): Collection
    {
        return Eleve::query()
            ->whereHas('inscriptions', fn ($q) => $q->where('classe_id', $creneau->affectation->classe_id)->where('statut', StatutInscription::Inscrit))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    private function ajouterMinutes(string $heure, int $minutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $heure));
        $total = $h * 60 + $m + $minutes;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }
}
