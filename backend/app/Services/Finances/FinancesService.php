<?php

namespace App\Services\Finances;

use App\Enums\StatutInscription;
use App\Models\AnneeScolaire;
use App\Models\Echeance;
use App\Models\Frais;
use App\Models\Inscription;
use App\Models\Paiement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Situation financière des inscriptions : frais du niveau, paiements valides, échéancier.
 * Tous les calculs se font en centimes (entiers) pour éviter les erreurs d'arrondi.
 */
class FinancesService
{
    public static function centimes(float|int|string|null $montant): int
    {
        return (int) round((float) $montant * 100);
    }

    public static function euros(int $centimes): float
    {
        return $centimes / 100;
    }

    public function totalFraisCentimes(Inscription $inscription): int
    {
        return self::centimes(Frais::where('niveau_id', $inscription->classe->niveau_id)
            ->where('annee_scolaire_id', $inscription->annee_scolaire_id)
            ->sum('montant'));
    }

    public function payeCentimes(Inscription $inscription): int
    {
        return self::centimes(Paiement::valides()->where('inscription_id', $inscription->id)->sum('montant'));
    }

    /** Part cumulée des frais exigible à une date (en %), selon l'échéancier de l'année. */
    public function pourcentageExigible(int $anneeId, CarbonImmutable $date): float
    {
        return (float) Echeance::where('annee_scolaire_id', $anneeId)
            ->whereDate('date_limite', '<', $date->toDateString())
            ->sum('pourcentage');
    }

    /**
     * Situation complète d'une inscription.
     *
     * @return array<string, mixed>
     */
    public function situation(Inscription $inscription, ?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::today();
        $inscription->loadMissing('classe', 'anneeScolaire');

        $frais = Frais::where('niveau_id', $inscription->classe->niveau_id)
            ->where('annee_scolaire_id', $inscription->annee_scolaire_id)
            ->orderBy('libelle')
            ->get();
        $total = self::centimes($frais->sum('montant'));
        $paye = $this->payeCentimes($inscription);

        // Montant de chaque tranche ; la dernière absorbe l'arrondi pour retomber sur le total
        $echeances = Echeance::where('annee_scolaire_id', $inscription->annee_scolaire_id)->orderBy('date_limite')->get();
        $cumul = 0;
        $pourcentageCumule = 0.0;
        $tranches = [];
        foreach ($echeances as $i => $echeance) {
            $pourcentageCumule += $echeance->pourcentage;
            $cumulVise = $i === $echeances->count() - 1 && abs($pourcentageCumule - 100) < 0.01
                ? $total
                : (int) round($total * $pourcentageCumule / 100);
            $montant = $cumulVise - $cumul;
            $cumul = $cumulVise;

            $tranches[] = [
                'libelle' => $echeance->libelle,
                'date_limite' => $echeance->date_limite->toDateString(),
                'pourcentage' => $echeance->pourcentage,
                'montant' => self::euros($montant),
                'cumul' => self::euros($cumul),
                'statut' => match (true) {
                    $paye >= $cumul => 'reglee',
                    $echeance->date_limite->lt($date) => 'en_retard',
                    default => 'a_venir',
                },
            ];
        }

        $exigible = (int) round($total * $this->pourcentageExigible($inscription->annee_scolaire_id, $date) / 100);

        return [
            'frais' => $frais->map(fn (Frais $f) => ['id' => $f->id, 'libelle' => $f->libelle, 'montant' => (float) $f->montant])->values(),
            'total' => self::euros($total),
            'paye' => self::euros($paye),
            'reste' => self::euros(max(0, $total - $paye)),
            'exigible' => self::euros($exigible),
            'retard' => self::euros(max(0, $exigible - $paye)),
            'echeances' => $tranches,
        ];
    }

    /**
     * Inscriptions de l'année avec leurs totaux (colonnes total_frais et total_paye, en euros),
     * pour la liste des impayés.
     *
     * @return Builder<Inscription>
     */
    public function requeteSoldes(AnneeScolaire $annee): Builder
    {
        $totalFrais = Frais::query()
            ->selectRaw('COALESCE(SUM(frais.montant), 0)')
            ->whereColumn('frais.niveau_id', 'classes.niveau_id')
            ->whereColumn('frais.annee_scolaire_id', 'inscriptions.annee_scolaire_id');

        $totalPaye = Paiement::query()
            ->selectRaw('COALESCE(SUM(paiements.montant), 0)')
            ->whereColumn('paiements.inscription_id', 'inscriptions.id')
            ->whereNull('paiements.annule_le');

        return Inscription::query()
            ->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->where('inscriptions.annee_scolaire_id', $annee->id)
            ->where('inscriptions.statut', StatutInscription::Inscrit)
            ->whereNull('eleves.deleted_at')
            ->select('inscriptions.*', 'eleves.nom as eleve_nom')
            ->selectSub($totalFrais, 'total_frais')
            ->selectSub($totalPaye, 'total_paye');
    }

    /**
     * Les mêmes inscriptions sous forme de sous-requête nommée « inscriptions » : on peut y filtrer
     * et trier sur total_frais / total_paye, paginer et charger les relations comme d'habitude.
     *
     * @return Builder<Inscription>
     */
    public function soldes(AnneeScolaire $annee, ?int $niveauId = null): Builder
    {
        $soldes = $this->requeteSoldes($annee)->when($niveauId, fn (Builder $q) => $q->where('classes.niveau_id', $niveauId));

        return Inscription::query()->fromSub($soldes->toBase(), 'inscriptions');
    }

    /**
     * Totaux de l'année : attendu, encaissé, reste à percevoir, retard, nombre de familles en retard.
     *
     * @return array{attendu: float, encaisse: float, reste: float, retard: float, en_retard: int, eleves: int, pourcentage_exigible: float}
     */
    public function totauxAnnee(AnneeScolaire $annee, ?CarbonImmutable $date = null): array
    {
        $pourcentage = $this->pourcentageExigible($annee->id, $date ?? CarbonImmutable::today());
        $soldes = $this->requeteSoldes($annee)->toBase();

        $ligne = DB::query()->fromSub($soldes, 's')->selectRaw(
            'COUNT(*) as eleves, COALESCE(SUM(total_frais), 0) as attendu, COALESCE(SUM(total_paye), 0) as encaisse,
             COALESCE(SUM(GREATEST(total_frais - total_paye, 0)), 0) as reste,
             COALESCE(SUM(GREATEST(ROUND(total_frais * ? / 100, 2) - total_paye, 0)), 0) as retard,
             COALESCE(SUM(ROUND(total_frais * ? / 100, 2) - total_paye > 0.004), 0) as en_retard',
            [$pourcentage, $pourcentage],
        )->first();

        return [
            'attendu' => (float) $ligne->attendu,
            'encaisse' => (float) $ligne->encaisse,
            'reste' => (float) $ligne->reste,
            'retard' => (float) $ligne->retard,
            'en_retard' => (int) $ligne->en_retard,
            'eleves' => (int) $ligne->eleves,
            'pourcentage_exigible' => $pourcentage,
        ];
    }
}
