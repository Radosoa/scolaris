<?php

namespace App\Services\Finances;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\Echeance;
use App\Models\Frais;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Frais par niveau et échéancier de l'année. */
class FraisService
{
    public function __construct(private readonly FinancesService $finances) {}

    /** @param  array{niveau_id: int, annee_scolaire_id: int, libelle: string, montant: float|string}  $donnees */
    public function creer(array $donnees): Frais
    {
        return Frais::create($donnees);
    }

    /** @param  array{libelle: string, montant: float|string}  $donnees */
    public function modifier(Frais $frais, array $donnees): Frais
    {
        return DB::transaction(function () use ($frais, $donnees) {
            $frais->update($donnees);
            $this->refuserSiTropPercu($frais);

            return $frais;
        });
    }

    public function supprimer(Frais $frais): void
    {
        DB::transaction(function () use ($frais) {
            $frais->delete();
            $this->refuserSiTropPercu($frais);
        });
    }

    /**
     * Remplace l'échéancier de l'année. Les pourcentages doivent totaliser 100 %
     * et les dates limites appartenir à l'année scolaire.
     *
     * @param  list<array{libelle: string, date_limite: string, pourcentage: float|string}>  $lignes
     * @return Collection<int, Echeance>
     */
    public function synchroniserEcheances(AnneeScolaire $annee, array $lignes): Collection
    {
        $total = array_sum(array_map(fn (array $l) => FinancesService::centimes($l['pourcentage']), $lignes));
        if ($lignes !== [] && $total !== 10000) {
            throw ValidationException::withMessages([
                'echeances' => 'Les tranches doivent totaliser 100 % (actuellement '.number_format($total / 100, 2, ',', ' ').' %).',
            ]);
        }

        $erreurs = [];
        foreach ($lignes as $i => $ligne) {
            if ($ligne['date_limite'] < $annee->date_debut->toDateString() || $ligne['date_limite'] > $annee->date_fin->toDateString()) {
                $erreurs["echeances.{$i}.date_limite"] = "La date limite doit appartenir à l'année {$annee->libelle}.";
            }
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        return DB::transaction(function () use ($annee, $lignes) {
            Echeance::where('annee_scolaire_id', $annee->id)->delete();
            foreach ($lignes as $ligne) {
                Echeance::create(['annee_scolaire_id' => $annee->id, ...$ligne]);
            }

            return Echeance::where('annee_scolaire_id', $annee->id)->orderBy('date_limite')->get();
        });
    }

    /**
     * Une baisse des frais ne doit pas laisser une famille avoir payé plus que le total dû.
     *
     * @throws RegleMetierException (la transaction appelante est annulée)
     */
    private function refuserSiTropPercu(Frais $frais): void
    {
        $annee = AnneeScolaire::findOrFail($frais->annee_scolaire_id);
        $tropPercu = $this->finances->soldes($annee, $frais->niveau_id)
            ->whereColumn('total_paye', '>', 'total_frais')
            ->count();

        if ($tropPercu > 0) {
            throw new RegleMetierException("Modification impossible : {$tropPercu} famille(s) auraient déjà payé plus que le nouveau total.");
        }
    }
}
