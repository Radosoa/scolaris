<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finances\ContexteFinances;
use Tests\TestCase;

class ParametresTest extends TestCase
{
    use ContexteFinances, RefreshDatabase;

    public function test_la_devise_par_defaut_vient_de_la_configuration(): void
    {
        config(['ecole.devise' => 'MGA']);
        $this->connecter(Role::Parent);

        $this->getJson('/api/parametres')
            ->assertOk()
            ->assertJsonPath('data.devise', ['code' => 'MGA', 'libelle' => 'Ariary malgache', 'symbole' => 'Ar', 'decimales' => 0])
            ->assertJsonFragment(['code' => 'USD', 'libelle' => 'Dollar américain', 'symbole' => '$', 'decimales' => 2]);
    }

    public function test_la_direction_change_la_devise(): void
    {
        $this->connecter(Role::Admin);

        $this->putJson('/api/parametres', ['devise' => 'MGA'])
            ->assertOk()
            ->assertJsonPath('data.devise.code', 'MGA');
        $this->getJson('/api/parametres')->assertJsonPath('data.devise.symbole', 'Ar');
        $this->assertDatabaseHas('parametres', ['cle' => 'devise', 'valeur' => 'MGA']);
    }

    public function test_seule_la_direction_change_la_devise(): void
    {
        $this->connecter(Role::Secretariat);
        $this->putJson('/api/parametres', ['devise' => 'USD'])->assertForbidden();
    }

    public function test_une_devise_inconnue_est_refusee(): void
    {
        $this->connecter(Role::Admin);
        $this->putJson('/api/parametres', ['devise' => 'BTC'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['devise' => 'Cette devise n\'est pas proposée.']);
    }

    public function test_les_montants_suivent_la_devise_choisie(): void
    {
        $this->preparerContexte();
        $this->connecter(Role::Admin);
        $this->putJson('/api/parametres', ['devise' => 'MGA'])->assertOk();

        // L'ariary n'a pas de décimales : 250,50 est refusé, et le reste à payer s'affiche en Ar
        $url = "/api/inscriptions/{$this->inscriptions[0]->id}/paiements";
        $this->postJson($url, ['montant' => 250.5, 'date' => '2026-10-01', 'mode' => 'especes'])
            ->assertJsonValidationErrors(['montant' => 'Montant sans décimales attendu.']);
        $this->postJson($url, ['montant' => 999999, 'date' => '2026-10-01', 'mode' => 'especes'])
            ->assertJsonValidationErrors(['montant' => 'Le montant dépasse le reste à payer (1 251 Ar).']);
    }
}
