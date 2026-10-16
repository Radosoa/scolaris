<?php

namespace App\Services\VieScolaire;

use App\Enums\TypeAbsence;
use App\Models\Absence;
use App\Models\Eleve;
use Illuminate\Support\Facades\DB;

/** Justification des absences et retards par la vie scolaire. */
class AbsenceService
{
    /**
     * Justifie (ou retire la justification d') une absence. Avec $journee, la décision
     * s'applique à toutes les absences et retards de l'élève ce jour-là (ex. certificat médical).
     *
     * @return int nombre d'absences mises à jour
     */
    public function justifier(Absence $absence, bool $justifiee, ?string $motif, bool $journee = false): int
    {
        $absences = $journee
            ? Absence::where('eleve_id', $absence->eleve_id)->whereDate('date', $absence->date)->get()
            : collect([$absence]);

        DB::transaction(function () use ($absences, $justifiee, $motif) {
            foreach ($absences as $a) {
                $a->fill(['justifiee' => $justifiee, 'motif' => $justifiee ? $motif : null])->save();
            }
        });

        return $absences->count();
    }

    /**
     * Totaux d'un élève : heures d'absence justifiées / non justifiées, retards.
     *
     * @return array{heures_justifiees: float, heures_non_justifiees: float, retards: int, a_justifier: int}
     */
    public function totaux(Eleve $eleve): array
    {
        $totaux = ['heures_justifiees' => 0.0, 'heures_non_justifiees' => 0.0, 'retards' => 0, 'a_justifier' => 0];

        foreach (Absence::where('eleve_id', $eleve->id)->get() as $absence) {
            if ($absence->type === TypeAbsence::Retard) {
                $totaux['retards']++;
            } else {
                $totaux[$absence->justifiee ? 'heures_justifiees' : 'heures_non_justifiees'] += $absence->dureeEnHeures();
            }
            if (! $absence->justifiee) {
                $totaux['a_justifier']++;
            }
        }

        return $totaux;
    }
}
