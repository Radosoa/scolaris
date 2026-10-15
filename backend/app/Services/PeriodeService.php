<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodeService
{
    /**
     * @param  array{annee_scolaire_id: int, libelle: string, ordre: int, date_debut: string, date_fin: string}  $donnees
     */
    public function creer(array $donnees): Periode
    {
        $periode = new Periode($donnees);
        $this->verifierDates($periode);
        $periode->save();

        return $periode;
    }

    /**
     * @param  array{libelle?: string, ordre?: int, date_debut?: string, date_fin?: string}  $donnees
     */
    public function modifier(Periode $periode, array $donnees): Periode
    {
        $this->refuserSiCloturee($periode, 'modifier');

        $periode->fill($donnees);
        $this->verifierDates($periode);
        $periode->save();

        return $periode;
    }

    public function supprimer(Periode $periode): void
    {
        $this->refuserSiCloturee($periode, 'supprimer');

        $periode->delete();
    }

    /** Une période clôturée n'accepte plus de saisie de notes et permet la génération des bulletins. */
    public function cloturer(Periode $periode): Periode
    {
        $periode->forceFill(['cloturee' => true])->save();

        return $periode;
    }

    public function rouvrir(Periode $periode): Periode
    {
        $bulletinsValides = DB::table('bulletins')
            ->where('periode_id', $periode->id)
            ->where('valide', true)
            ->exists();

        if ($bulletinsValides) {
            throw new RegleMetierException('Impossible de rouvrir la période : des bulletins ont déjà été validés.');
        }

        $periode->forceFill(['cloturee' => false])->save();

        return $periode;
    }

    /**
     * La période doit être comprise dans son année et ne chevaucher aucune autre période.
     *
     * @throws ValidationException
     */
    private function verifierDates(Periode $periode): void
    {
        /** @var AnneeScolaire $annee */
        $annee = AnneeScolaire::findOrFail($periode->annee_scolaire_id);

        if ($periode->date_debut->lt($annee->date_debut) || $periode->date_fin->gt($annee->date_fin)) {
            throw ValidationException::withMessages([
                'date_debut' => sprintf(
                    'La période doit être comprise entre le %s et le %s.',
                    $annee->date_debut->format('d/m/Y'),
                    $annee->date_fin->format('d/m/Y'),
                ),
            ]);
        }

        $chevauchement = Periode::query()
            ->where('annee_scolaire_id', $annee->id)
            ->when($periode->exists, fn ($q) => $q->whereKeyNot($periode->id))
            ->where('date_debut', '<=', $periode->date_fin)
            ->where('date_fin', '>=', $periode->date_debut)
            ->first();

        if ($chevauchement) {
            throw ValidationException::withMessages([
                'date_debut' => "Les dates chevauchent la période « {$chevauchement->libelle} ».",
            ]);
        }
    }

    private function refuserSiCloturee(Periode $periode, string $action): void
    {
        if ($periode->cloturee) {
            throw new RegleMetierException("Impossible de {$action} une période clôturée. Rouvrez-la d'abord.");
        }
    }
}
