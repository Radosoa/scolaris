<?php

namespace App\Services\Bulletins;

use App\Enums\StatutInscription;
use App\Enums\TypeAbsence;
use App\Exceptions\RegleMetierException;
use App\Models\Absence;
use App\Models\Affectation;
use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calcul, validation et appréciations des bulletins d'une classe pour une période.
 * Les règles de calcul elles-mêmes sont dans MoyenneCalculator.
 */
class BulletinService
{
    public function __construct(private readonly MoyenneCalculator $calcul) {}

    /**
     * Calcule (ou recalcule) les bulletins de tous les élèves inscrits dans la classe.
     * Les appréciations déjà saisies sont conservées.
     *
     * @return Collection<int, Bulletin>
     */
    public function calculer(Classe $classe, Periode $periode): Collection
    {
        $this->verifierPeriode($classe, $periode);
        $this->refuserSiValides($classe, $periode);

        $inscriptions = $classe->inscriptions()
            ->where('statut', StatutInscription::Inscrit)
            ->get();

        if ($inscriptions->isEmpty()) {
            throw new RegleMetierException('Aucun élève inscrit dans cette classe : rien à calculer.');
        }

        $coefficients = Coefficient::where('niveau_id', $classe->niveau_id)->pluck('valeur', 'matiere_id');
        $matiereIds = Affectation::where('classe_id', $classe->id)
            ->pluck('matiere_id')
            ->filter(fn (int $id) => isset($coefficients[$id]))
            ->values();

        $eleveIds = $inscriptions->pluck('eleve_id')->all();
        $notes = $this->notesParEleveEtMatiere($classe, $periode, $eleveIds);

        // Moyennes par matière, puis générales (valeurs non arrondies)
        $moyennes = [];   // [eleve_id][matiere_id] => ?float
        $generales = [];  // [eleve_id] => ?float
        foreach ($eleveIds as $eleveId) {
            foreach ($matiereIds as $matiereId) {
                $moyennes[$eleveId][$matiereId] = $this->calcul->moyenneMatiere($notes[$eleveId][$matiereId] ?? []);
            }
            $generales[$eleveId] = $this->calcul->moyenneGenerale(
                $matiereIds->map(fn (int $m) => ['moyenne' => $moyennes[$eleveId][$m], 'coefficient' => (float) $coefficients[$m]])
            );
        }

        $rangs = $this->calcul->rangs($generales);
        $statsClasse = $this->calcul->statistiques($generales);

        $rangsMatiere = [];
        $statsMatiere = [];
        foreach ($matiereIds as $matiereId) {
            $colonne = array_map(fn (array $parMatiere) => $parMatiere[$matiereId], $moyennes);
            $rangsMatiere[$matiereId] = $this->calcul->rangs($colonne);
            $statsMatiere[$matiereId] = $this->calcul->statistiques($colonne);
        }

        $absences = $this->absences($periode, $eleveIds);

        return DB::transaction(function () use (
            $classe, $periode, $inscriptions, $matiereIds, $coefficients, $moyennes, $generales,
            $rangs, $statsClasse, $rangsMatiere, $statsMatiere, $absences
        ) {
            // Élèves sortis de la classe depuis un calcul précédent (transfert, abandon)
            Bulletin::deClasse($classe, $periode)->whereNotIn('inscription_id', $inscriptions->pluck('id'))->delete();

            return $inscriptions->map(function (Inscription $inscription) use (
                $periode, $inscriptions, $matiereIds, $coefficients, $moyennes, $generales,
                $rangs, $statsClasse, $rangsMatiere, $statsMatiere, $absences
            ) {
                $eleveId = $inscription->eleve_id;
                $bulletin = Bulletin::updateOrCreate(
                    ['inscription_id' => $inscription->id, 'periode_id' => $periode->id],
                    [
                        'moyenne' => $generales[$eleveId],
                        'rang' => $rangs[$eleveId],
                        'effectif' => $inscriptions->count(),
                        'mention' => $this->calcul->mention($generales[$eleveId]),
                        'moyenne_classe' => $statsClasse['moyenne'],
                        'moyenne_max' => $statsClasse['max'],
                        'moyenne_min' => $statsClasse['min'],
                        'heures_abs_justifiees' => $absences[$eleveId]['justifiees'] ?? 0,
                        'heures_abs_non_justifiees' => $absences[$eleveId]['non_justifiees'] ?? 0,
                        'nb_retards' => $absences[$eleveId]['retards'] ?? 0,
                    ],
                );
                $bulletin->forceFill(['calcule_le' => now()])->save();

                $bulletin->lignes()->whereNotIn('matiere_id', $matiereIds)->delete();
                foreach ($matiereIds as $matiereId) {
                    BulletinLigne::updateOrCreate(
                        ['bulletin_id' => $bulletin->id, 'matiere_id' => $matiereId],
                        [
                            'moyenne' => $moyennes[$eleveId][$matiereId],
                            'coefficient' => $coefficients[$matiereId],
                            'rang_matiere' => $rangsMatiere[$matiereId][$eleveId],
                            'moyenne_classe' => $statsMatiere[$matiereId]['moyenne'],
                            'moyenne_max' => $statsMatiere[$matiereId]['max'],
                            'moyenne_min' => $statsMatiere[$matiereId]['min'],
                        ],
                    );
                }

                return $bulletin;
            });
        });
    }

    /** Valide tous les bulletins de la classe : ils deviennent consultables et ne sont plus modifiables. */
    public function valider(Classe $classe, Periode $periode, User $par): int
    {
        $this->verifierPeriode($classe, $periode);
        $this->refuserSiValides($classe, $periode);

        $nombre = Bulletin::deClasse($classe, $periode)->count();
        if ($nombre === 0) {
            throw new RegleMetierException('Calculez les bulletins avant de les valider.');
        }

        Bulletin::deClasse($classe, $periode)->update([
            'valide' => true,
            'valide_par' => $par->id,
            'valide_le' => now(),
        ]);

        return $nombre;
    }

    /** Appréciation générale de la direction (bulletin non validé uniquement). */
    public function definirAppreciationGenerale(Bulletin $bulletin, ?string $appreciation): Bulletin
    {
        $this->refuserSiValide($bulletin);

        $bulletin->forceFill(['appreciation_generale' => $appreciation])->save();

        return $bulletin;
    }

    /**
     * Lignes d'une matière (affectation) pour la saisie des appréciations par le professeur.
     *
     * @return Collection<int, BulletinLigne>
     */
    public function lignesDeMatiere(Affectation $affectation, Periode $periode): Collection
    {
        return BulletinLigne::query()
            ->where('matiere_id', $affectation->matiere_id)
            ->whereHas('bulletin', fn ($q) => $q->deClasse($affectation->classe, $periode))
            ->with('bulletin.inscription.eleve')
            ->get()
            ->sortBy(fn (BulletinLigne $l) => [$l->bulletin->inscription->eleve->nom, $l->bulletin->inscription->eleve->prenom])
            ->values();
    }

    /**
     * Appréciations du professeur sur sa matière, en lot.
     *
     * @param  list<array{ligne_id: int, appreciation: string|null}>  $appreciations
     */
    public function enregistrerAppreciations(Affectation $affectation, Periode $periode, array $appreciations): void
    {
        $lignes = $this->lignesDeMatiere($affectation, $periode)->keyBy('id');
        if ($lignes->isEmpty()) {
            throw new RegleMetierException('Les bulletins de cette classe n\'ont pas encore été calculés.');
        }
        if ($lignes->first()->bulletin->valide) {
            throw new RegleMetierException('Les bulletins de cette classe sont validés : les appréciations ne sont plus modifiables.');
        }

        $erreurs = [];
        foreach ($appreciations as $index => $saisie) {
            if (! $lignes->has($saisie['ligne_id'])) {
                $erreurs["appreciations.{$index}.ligne_id"] = 'Cette ligne n\'appartient pas à votre matière dans cette classe.';
            }
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        DB::transaction(function () use ($lignes, $appreciations) {
            foreach ($appreciations as $saisie) {
                $lignes[$saisie['ligne_id']]->forceFill(['appreciation' => $saisie['appreciation']])->save();
            }
        });
    }

    /**
     * État des bulletins d'une classe pour l'écran de la direction.
     *
     * @return array{etat: string, calcule_le: string|null, valide_le: string|null, nombre: int, pdf_generes: int}
     */
    public function etat(Classe $classe, Periode $periode): array
    {
        $resume = Bulletin::deClasse($classe, $periode)
            ->selectRaw('COUNT(*) as nombre, MAX(calcule_le) as calcule_le, MAX(valide_le) as valide_le, SUM(valide) as valides, COUNT(pdf_path) as pdf')
            ->toBase()
            ->first();

        $nombre = (int) $resume->nombre;

        return [
            'etat' => match (true) {
                $nombre === 0 => 'non_calcule',
                (int) $resume->valides === $nombre => 'valide',
                default => 'brouillon',
            },
            'calcule_le' => $resume->calcule_le,
            'valide_le' => $resume->valide_le,
            'nombre' => $nombre,
            'pdf_generes' => (int) $resume->pdf,
        ];
    }

    /**
     * Notes de la période, regroupées par élève et par matière, au format du calculateur.
     *
     * @param  list<int>  $eleveIds
     * @return array<int, array<int, list<array{valeur: float|null, bareme: float, categorie: string, absent: bool}>>>
     */
    private function notesParEleveEtMatiere(Classe $classe, Periode $periode, array $eleveIds): array
    {
        $evaluations = Evaluation::query()
            ->where('periode_id', $periode->id)
            ->whereHas('affectation', fn ($q) => $q->where('classe_id', $classe->id))
            ->with(['affectation:id,matiere_id', 'notes' => fn ($q) => $q->whereIn('eleve_id', $eleveIds)])
            ->get();

        $notes = [];
        foreach ($evaluations as $evaluation) {
            foreach ($evaluation->notes as $note) {
                $notes[$note->eleve_id][$evaluation->affectation->matiere_id][] = [
                    'valeur' => $note->valeur,
                    'bareme' => $evaluation->bareme,
                    'categorie' => $evaluation->type->categorie(),
                    'absent' => $note->absent,
                ];
            }
        }

        return $notes;
    }

    /**
     * Heures d'absence (justifiées / non justifiées) et nombre de retards sur la période.
     *
     * @param  list<int>  $eleveIds
     * @return array<int, array{justifiees: float, non_justifiees: float, retards: int}>
     */
    private function absences(Periode $periode, array $eleveIds): array
    {
        $totaux = [];
        Absence::query()
            ->whereIn('eleve_id', $eleveIds)
            ->whereBetween('date', [$periode->date_debut->toDateString(), $periode->date_fin->toDateString()])
            ->get()
            ->each(function (Absence $absence) use (&$totaux) {
                $total = &$totaux[$absence->eleve_id];
                $total ??= ['justifiees' => 0.0, 'non_justifiees' => 0.0, 'retards' => 0];

                if ($absence->type === TypeAbsence::Retard) {
                    $total['retards']++;
                } else {
                    $total[$absence->justifiee ? 'justifiees' : 'non_justifiees'] += $absence->dureeEnHeures();
                }
            });

        return $totaux;
    }

    /** La période doit être clôturée et appartenir à l'année de la classe. */
    private function verifierPeriode(Classe $classe, Periode $periode): void
    {
        if ($periode->annee_scolaire_id !== $classe->annee_scolaire_id) {
            throw ValidationException::withMessages([
                'periode_id' => 'Cette période n\'appartient pas à l\'année scolaire de la classe.',
            ]);
        }

        if (! $periode->cloturee) {
            throw new RegleMetierException("La période « {$periode->libelle} » n'est pas clôturée : clôturez-la avant de générer les bulletins.");
        }
    }

    private function refuserSiValides(Classe $classe, Periode $periode): void
    {
        if (Bulletin::deClasse($classe, $periode)->where('valide', true)->exists()) {
            throw new RegleMetierException('Les bulletins de cette classe sont déjà validés : ils ne sont plus modifiables.');
        }
    }

    private function refuserSiValide(Bulletin $bulletin): void
    {
        if ($bulletin->valide) {
            throw new RegleMetierException('Ce bulletin est validé : il n\'est plus modifiable.');
        }
    }
}
