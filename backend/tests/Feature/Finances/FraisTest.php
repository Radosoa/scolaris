<?php

namespace Tests\Feature\Finances;

use App\Enums\Role;
use App\Models\Frais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FraisTest extends TestCase
{
    use ContexteFinances, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
    }

    public function test_la_direction_configure_les_frais_par_niveau(): void
    {
        $this->connecter(Role::Admin);

        $this->getJson('/api/frais')
            ->assertOk()
            ->assertJsonPath('meta.annee.libelle', '2026-2027')
            ->assertJsonCount(3, 'meta.echeances')
            ->assertJsonPath('data.0.total', 1250.5)
            ->assertJsonPath('data.0.frais.0.libelle', 'Cantine');

        $id = $this->postJson('/api/frais', [
            'niveau_id' => $this->classe->niveau_id, 'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Fournitures', 'montant' => 45,
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/frais', [
            'niveau_id' => $this->classe->niveau_id, 'annee_scolaire_id' => $this->annee->id, 'libelle' => 'Fournitures', 'montant' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors(['libelle' => 'Ce niveau a déjà une ligne de frais portant ce libellé.']);

        $this->putJson("/api/frais/{$id}", ['libelle' => 'Fournitures scolaires', 'montant' => 50])
            ->assertOk()
            ->assertJsonPath('data.montant', 50);
        $this->deleteJson("/api/frais/{$id}")->assertNoContent();
    }

    public function test_une_baisse_des_frais_ne_peut_pas_rendre_un_paiement_superieur_au_total(): void
    {
        $this->payer(0, 1200);
        $this->connecter(Role::Admin);
        $cantine = Frais::where('libelle', 'Cantine')->firstOrFail();

        $this->deleteJson("/api/frais/{$cantine->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Modification impossible : 1 famille(s) auraient déjà payé plus que le nouveau total.');
        $this->putJson("/api/frais/{$cantine->id}", ['libelle' => 'Cantine', 'montant' => 100])->assertConflict();
        $this->assertEquals(250.50, (float) $cantine->fresh()->montant);

        $this->putJson("/api/frais/{$cantine->id}", ['libelle' => 'Cantine', 'montant' => 200])->assertOk();
    }

    public function test_l_echeancier_doit_totaliser_cent_pour_cent_dans_l_annee(): void
    {
        $this->connecter(Role::Admin);
        $url = "/api/annees-scolaires/{$this->annee->id}/echeances";

        $this->putJson($url, ['echeances' => [
            ['libelle' => 'Tranche 1', 'date_limite' => '2026-10-15', 'pourcentage' => 50],
            ['libelle' => 'Tranche 2', 'date_limite' => '2027-02-15', 'pourcentage' => 40],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['echeances' => 'Les tranches doivent totaliser 100 % (actuellement 90,00 %).']);

        $this->putJson($url, ['echeances' => [
            ['libelle' => 'Tranche 1', 'date_limite' => '2026-10-15', 'pourcentage' => 50],
            ['libelle' => 'Tranche 2', 'date_limite' => '2027-09-15', 'pourcentage' => 50],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['echeances.1.date_limite' => 'La date limite doit appartenir à l\'année 2026-2027.']);

        $this->putJson($url, ['echeances' => [
            ['libelle' => 'Solde', 'date_limite' => '2027-02-15', 'pourcentage' => 66.67],
            ['libelle' => 'Acompte', 'date_limite' => '2026-10-15', 'pourcentage' => 33.33],
        ]])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.libelle', 'Acompte');

        // Le 16/10, l'acompte (échu le 15) est exigible
        $this->getJson("/api/eleves/{$this->inscriptions[0]->eleve_id}/finances")->assertJsonPath('data.exigible', 416.79);
    }

    public function test_le_secretariat_consulte_les_frais_sans_les_modifier(): void
    {
        $this->connecter(Role::Secretariat);
        $frais = Frais::firstOrFail();

        $this->getJson('/api/frais')->assertOk();
        $this->postJson('/api/frais', ['niveau_id' => $this->classe->niveau_id, 'annee_scolaire_id' => $this->annee->id, 'libelle' => 'X', 'montant' => 1])->assertForbidden();
        $this->putJson("/api/frais/{$frais->id}", ['libelle' => 'X', 'montant' => 1])->assertForbidden();
        $this->deleteJson("/api/frais/{$frais->id}")->assertForbidden();
        $this->putJson("/api/annees-scolaires/{$this->annee->id}/echeances", ['echeances' => []])->assertForbidden();

        $this->connecter(Role::Parent);
        $this->getJson('/api/frais')->assertForbidden();
    }
}
