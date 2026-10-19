<?php

namespace App\Services\Finances;

use App\Enums\Role;
use App\Enums\StatutInscription;
use App\Models\AnneeScolaire;
use App\Models\Echeance;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\RecapRetards;
use App\Notifications\RetardEcolage;
use App\Services\ParametresService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Avertissements de retard d'écolage, lancés chaque matin (scolaris:relancer-impayes).
 * Une famille est avertie une seule fois par tranche impayée ; la direction et le secrétariat
 * reçoivent un récapitulatif des nouveaux retards du jour.
 */
class RelancesService
{
    public function __construct(
        private readonly FinancesService $finances,
        private readonly ParametresService $parametres,
    ) {}

    /** @return array{familles: int, montant: float} */
    public function relancer(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::today();
        $annee = AnneeScolaire::active()->first();
        // Dernière tranche échue : c'est elle qui déclenche l'avertissement
        $echeance = $annee
            ? Echeance::where('annee_scolaire_id', $annee->id)->whereDate('date_limite', '<', $date->toDateString())->orderByDesc('date_limite')->first()
            : null;
        if (! $echeance) {
            return ['familles' => 0, 'montant' => 0.0];
        }

        $devise = $this->parametres->devise();
        $dejaAvertis = DB::table('relances_impayes')->where('echeance_id', $echeance->id)->pluck('inscription_id')->flip();
        $nouveaux = [];
        $totalRetard = 0;

        $inscriptions = Inscription::with('eleve.tuteurs.user', 'classe', 'anneeScolaire')
            ->where('annee_scolaire_id', $annee->id)
            ->where('statut', StatutInscription::Inscrit)
            ->whereHas('eleve')
            ->whereNotIn('id', $dejaAvertis->keys())
            ->get();

        foreach ($inscriptions as $inscription) {
            $retard = FinancesService::centimes($this->finances->situation($inscription, $date)['retard']);
            if ($retard <= 0) {
                continue;
            }

            DB::table('relances_impayes')->insert([
                'inscription_id' => $inscription->id,
                'echeance_id' => $echeance->id,
                'montant_retard' => FinancesService::euros($retard),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $eleve = $inscription->eleve->nomComplet();
            $parents = $inscription->eleve->tuteurs->pluck('user')->filter(fn (?User $u) => $u?->actif);
            Notification::send($parents, new RetardEcolage($eleve, $echeance->libelle, $echeance->date_limite->format('d/m/Y'), $devise->formater($retard)));

            $nouveaux[] = "{$eleve} ({$inscription->classe->nom})";
            $totalRetard += $retard;
        }

        if ($nouveaux) {
            $personnel = User::role([Role::Admin->value, Role::Secretariat->value])->where('actif', true)->get();
            Notification::send($personnel, new RecapRetards($nouveaux, $devise->formater($totalRetard)));
        }

        return ['familles' => count($nouveaux), 'montant' => FinancesService::euros($totalRetard)];
    }
}
