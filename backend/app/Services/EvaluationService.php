<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Periode;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EvaluationService
{
    /**
     * @param  array{periode_id: int, type: string, titre: string, date: string, bareme: float}  $donnees
     */
    public function creer(Affectation $affectation, array $donnees): Evaluation
    {
        $periode = Periode::findOrFail($donnees['periode_id']);
        $this->verifierPeriode($affectation, $periode, Carbon::parse($donnees['date']));

        return Evaluation::create([...$donnees, 'affectation_id' => $affectation->id]);
    }

    /**
     * @param  array{periode_id: int, type: string, titre: string, date: string, bareme: float}  $donnees
     */
    public function modifier(Evaluation $evaluation, array $donnees): Evaluation
    {
        $this->refuserSiVerrouillee($evaluation);

        $periode = Periode::findOrFail($donnees['periode_id']);
        $this->verifierPeriode($evaluation->affectation, $periode, Carbon::parse($donnees['date']));

        // Baisser le barème rendrait des notes déjà saisies supérieures au maximum
        $noteMax = $evaluation->notes()->max('valeur');
        if ($noteMax !== null && (float) $noteMax > (float) $donnees['bareme']) {
            throw ValidationException::withMessages([
                'bareme' => "Une note de {$this->format($noteMax)} a déjà été saisie : le barème ne peut pas être inférieur.",
            ]);
        }

        $evaluation->update($donnees);

        return $evaluation;
    }

    public function supprimer(Evaluation $evaluation): void
    {
        $this->refuserSiVerrouillee($evaluation);

        $evaluation->delete();
    }

    /** @throws RegleMetierException */
    public function refuserSiVerrouillee(Evaluation $evaluation): void
    {
        if ($evaluation->estVerrouillee()) {
            throw new RegleMetierException(
                "La période « {$evaluation->periode->libelle} » est clôturée : les notes ne sont plus modifiables."
            );
        }
    }

    /**
     * La période doit être ouverte, appartenir à l'année de la classe et contenir la date.
     */
    private function verifierPeriode(Affectation $affectation, Periode $periode, Carbon $date): void
    {
        if ($periode->annee_scolaire_id !== $affectation->classe->annee_scolaire_id) {
            throw ValidationException::withMessages([
                'periode_id' => 'Cette période n\'appartient pas à l\'année scolaire de la classe.',
            ]);
        }

        if ($periode->cloturee) {
            throw new RegleMetierException("La période « {$periode->libelle} » est clôturée : impossible d'y ajouter une évaluation.");
        }

        if (! $date->betweenIncluded($periode->date_debut, $periode->date_fin)) {
            throw ValidationException::withMessages([
                'date' => sprintf(
                    'La date doit être comprise dans la période (%s → %s).',
                    $periode->date_debut->format('d/m/Y'),
                    $periode->date_fin->format('d/m/Y'),
                ),
            ]);
        }
    }

    private function format(float|string $valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, ',', ''), '0'), ',');
    }
}
