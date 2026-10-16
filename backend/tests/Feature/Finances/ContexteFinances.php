<?php

namespace Tests\Feature\Finances;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Echeance;
use App\Models\Eleve;
use App\Models\Frais;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;

/**
 * Contexte commun, au 16/10/2026 : année 2026-2027 active, 6e A (Albert, Bernard, Durand).
 * Frais de 6e : scolarité 1 000 € + cantine 250,50 € = 1 250,50 €.
 * Échéancier : 40 % au 30/09/2026 (déjà passé), 30 % au 15/01/2027, 30 % au 15/04/2027.
 */
trait ContexteFinances
{
    protected AnneeScolaire $annee;

    protected Classe $classe;

    /** @var list<Inscription> */
    protected array $inscriptions;

    protected function preparerContexte(): void
    {
        $this->travelTo('2026-10-16 10:00:00');

        $this->annee = AnneeScolaire::factory()->debutant(2026)->active()->create();
        $this->classe = Classe::factory()->for($this->annee)->create(['nom' => '6e A']);

        foreach ([['Scolarité', 1000], ['Cantine', 250.50]] as [$libelle, $montant]) {
            Frais::create(['niveau_id' => $this->classe->niveau_id, 'annee_scolaire_id' => $this->annee->id, 'libelle' => $libelle, 'montant' => $montant]);
        }
        foreach ([['Tranche 1', '2026-09-30', 40], ['Tranche 2', '2027-01-15', 30], ['Tranche 3', '2027-04-15', 30]] as [$libelle, $date, $pourcentage]) {
            Echeance::create(['annee_scolaire_id' => $this->annee->id, 'libelle' => $libelle, 'date_limite' => $date, 'pourcentage' => $pourcentage]);
        }

        $this->inscriptions = collect(['Albert', 'Bernard', 'Durand'])
            ->map(fn (string $nom) => Inscription::factory()->for($this->classe)->for(Eleve::factory()->state(['nom' => $nom]))->create())
            ->all();
    }

    protected function payer(int $eleve, float $montant, ?string $annuleLe = null, string $date = '2026-09-15'): Paiement
    {
        $paiement = new Paiement(['inscription_id' => $this->inscriptions[$eleve]->id, 'montant' => $montant, 'date' => $date, 'mode' => 'especes']);
        $paiement->forceFill([
            'numero_recu' => 'T-'.uniqid(),
            'encaisse_par' => User::factory()->create()->id,
            'annule_le' => $annuleLe,
        ])->save();

        return $paiement;
    }
}
