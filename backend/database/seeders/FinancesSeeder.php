<?php

namespace Database\Seeders;

use App\Enums\StatutInscription;
use App\Models\AnneeScolaire;
use App\Models\Echeance;
use App\Models\Frais;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Finances\FinancesService;
use App\Services\Finances\PaiementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Frais de l'année active par niveau, échéancier en trois tranches (40 / 30 / 30 %)
 * et paiements de la rentrée à aujourd'hui : familles soldées, à jour, en retard ou
 * n'ayant rien versé, plus deux chèques rejetés (paiements annulés).
 * Ne fait rien si des frais existent déjà.
 */
class FinancesSeeder extends Seeder
{
    /** Libellé => montant par niveau (6e, 5e, 4e, 3e) */
    private const FRAIS = [
        'Scolarité' => [1200, 1250, 1300, 1350],
        'Cantine' => [520, 520, 520, 520],
        'Fournitures et sorties' => [80, 80, 95, 120],
    ];

    public function run(PaiementService $paiements, FinancesService $finances): void
    {
        if (Frais::exists()) {
            $this->command?->info('Des frais existent déjà : FinancesSeeder ignoré.');

            return;
        }

        $annee = AnneeScolaire::active()->with('classes.niveau')->firstOrFail();
        $debut = $annee->date_debut->year;
        $secretariat = User::where('email', 'secretariat@scolaris.test')->firstOrFail();

        foreach ($annee->classes->pluck('niveau')->unique('id') as $niveau) {
            foreach (self::FRAIS as $libelle => $montants) {
                Frais::create([
                    'niveau_id' => $niveau->id,
                    'annee_scolaire_id' => $annee->id,
                    'libelle' => $libelle,
                    'montant' => $montants[$niveau->ordre - 1] ?? end($montants),
                ]);
            }
        }

        foreach ([['Tranche 1', "{$debut}-09-30", 40], ['Tranche 2', ($debut + 1).'-01-15', 30], ['Tranche 3', ($debut + 1).'-04-15', 30]] as [$libelle, $date, $pourcentage]) {
            Echeance::create(['annee_scolaire_id' => $annee->id, 'libelle' => $libelle, 'date_limite' => $date, 'pourcentage' => $pourcentage]);
        }

        mt_srand(4242);
        $aujourdhui = CarbonImmutable::today();
        $rentree = CarbonImmutable::parse($annee->date_debut);
        $demo = Inscription::whereHas('eleve.user', fn ($q) => $q->where('email', 'eleve@scolaris.test'))->value('id');

        $inscriptions = Inscription::with('classe')
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut', StatutInscription::Inscrit)
            ->get();

        $annules = 0;
        foreach ($inscriptions as $inscription) {
            $total = $finances->totalFraisCentimes($inscription) / 100;
            $tranche1 = round($total * 0.4, 2);
            $profil = $inscription->id === $demo ? 'tranche1' : $this->profil();

            $versements = match ($profil) {
                'solde' => [$total],
                'tranche1' => [$tranche1],
                'partiel' => [round($tranche1 * mt_rand(30, 80) / 100, 2)],
                'deux_fois' => [round($tranche1 / 2, 2), $tranche1 - round($tranche1 / 2, 2)],
                default => [],
            };

            foreach ($versements as $i => $montant) {
                // La plupart paient en septembre, quelques retardataires en octobre
                $date = $rentree->addDays(mt_rand(0, 100) <= 75 ? mt_rand(0, 28) : mt_rand(29, 45))->addDays($i * 21)->min($aujourdhui);
                $mode = $this->mode();
                $paiement = $paiements->encaisser($inscription, [
                    'montant' => $montant,
                    'date' => $date->toDateString(),
                    'mode' => $mode,
                    'reference' => $mode === 'especes' ? null : strtoupper($mode === 'cheque' ? 'CHQ '.mt_rand(1000000, 9999999) : substr($mode, 0, 3).'-'.mt_rand(100000, 999999)),
                ], $secretariat);

                // Deux chèques rejetés : la famille a ensuite payé par virement
                if ($mode === 'cheque' && $annules < 2 && $profil === 'tranche1') {
                    $paiements->annuler($paiement, 'Chèque rejeté par la banque (provision insuffisante)', $secretariat)
                        ->forceFill(['annule_le' => $date->addDays(7)->min($aujourdhui)->setTime(10, 0)])
                        ->save();
                    $paiements->encaisser($inscription, [
                        'montant' => $montant,
                        'date' => $date->addDays(7)->min($aujourdhui)->toDateString(),
                        'mode' => 'virement',
                        'reference' => 'VIR-'.mt_rand(100000, 999999),
                    ], $secretariat);
                    $annules++;
                }
            }
        }
    }

    private function profil(): string
    {
        $tirage = mt_rand(1, 100);

        return match (true) {
            $tirage <= 15 => 'solde',
            $tirage <= 65 => 'tranche1',
            $tirage <= 75 => 'deux_fois',
            $tirage <= 87 => 'partiel',
            default => 'rien',
        };
    }

    private function mode(): string
    {
        $tirage = mt_rand(1, 100);

        return match (true) {
            $tirage <= 45 => 'virement',
            $tirage <= 70 => 'cheque',
            $tirage <= 90 => 'carte',
            default => 'especes',
        };
    }
}
