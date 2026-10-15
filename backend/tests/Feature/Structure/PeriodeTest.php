<?php

namespace Tests\Feature\Structure;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeriodeTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connecter(Role::Admin);
        $this->annee = AnneeScolaire::factory()->debutant(2025)->create(); // 01/09/2025 → 15/07/2026
    }

    private function donnees(array $surcharges = []): array
    {
        return [
            'annee_scolaire_id' => $this->annee->id,
            'libelle' => 'Trimestre 1',
            'ordre' => 1,
            'date_debut' => '2025-09-01',
            'date_fin' => '2025-11-30',
            ...$surcharges,
        ];
    }

    public function test_creation_d_une_periode(): void
    {
        $this->postJson('/api/periodes', $this->donnees())
            ->assertCreated()
            ->assertJsonPath('data.libelle', 'Trimestre 1')
            ->assertJsonPath('data.cloturee', false);
    }

    public function test_la_periode_doit_etre_comprise_dans_l_annee(): void
    {
        $this->postJson('/api/periodes', $this->donnees(['date_debut' => '2025-08-15']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_debut.0', 'La période doit être comprise entre le 01/09/2025 et le 15/07/2026.');
    }

    public function test_les_periodes_ne_se_chevauchent_pas(): void
    {
        Periode::factory()->for($this->annee)->create(['date_debut' => '2025-09-01', 'date_fin' => '2025-11-30']);

        $this->postJson('/api/periodes', $this->donnees(['ordre' => 2, 'libelle' => 'Trimestre 2', 'date_debut' => '2025-11-15', 'date_fin' => '2026-02-28']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_debut.0', 'Les dates chevauchent la période « Trimestre 1 ».');

        $this->postJson('/api/periodes', $this->donnees(['ordre' => 2, 'libelle' => 'Trimestre 2', 'date_debut' => '2025-12-01', 'date_fin' => '2026-02-28']))
            ->assertCreated();
    }

    public function test_l_ordre_est_unique_dans_une_annee(): void
    {
        Periode::factory()->for($this->annee)->create();

        $this->postJson('/api/periodes', $this->donnees(['date_debut' => '2025-12-01', 'date_fin' => '2026-02-28']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ordre');
    }

    public function test_une_periode_modifiee_ne_se_compare_pas_a_elle_meme(): void
    {
        $periode = Periode::factory()->for($this->annee)->create(['date_debut' => '2025-09-01', 'date_fin' => '2025-11-30']);

        $this->putJson("/api/periodes/{$periode->id}", collect($this->donnees(['libelle' => 'T1', 'date_fin' => '2025-12-15']))->except('annee_scolaire_id')->all())
            ->assertOk()
            ->assertJsonPath('data.libelle', 'T1')
            ->assertJsonPath('data.date_fin', '2025-12-15');
    }

    public function test_cloturer_et_rouvrir_une_periode(): void
    {
        $periode = Periode::factory()->for($this->annee)->create();

        $this->postJson("/api/periodes/{$periode->id}/cloturer")->assertOk()->assertJsonPath('data.cloturee', true);
        $this->postJson("/api/periodes/{$periode->id}/rouvrir")->assertOk()->assertJsonPath('data.cloturee', false);
    }

    public function test_une_periode_cloturee_n_est_ni_modifiable_ni_supprimable(): void
    {
        $periode = Periode::factory()->for($this->annee)->cloturee()->create();

        $this->putJson("/api/periodes/{$periode->id}", collect($this->donnees())->except('annee_scolaire_id')->all())
            ->assertConflict();
        $this->deleteJson("/api/periodes/{$periode->id}")->assertConflict();
    }

    public function test_une_periode_avec_bulletins_valides_ne_peut_pas_etre_rouverte(): void
    {
        $periode = Periode::factory()->for($this->annee)->cloturee()->create();
        $classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id]);
        $eleveId = DB::table('eleves')->insertGetId([
            'matricule' => 'E0001', 'nom' => 'Durand', 'prenom' => 'Léa', 'sexe' => 'F', 'date_naissance' => '2012-03-04',
        ]);
        $inscriptionId = DB::table('inscriptions')->insertGetId([
            'eleve_id' => $eleveId, 'classe_id' => $classe->id, 'annee_scolaire_id' => $this->annee->id, 'date_inscription' => '2025-09-01',
        ]);
        DB::table('bulletins')->insert([
            'inscription_id' => $inscriptionId, 'periode_id' => $periode->id, 'effectif' => 1, 'valide' => true,
        ]);

        $this->postJson("/api/periodes/{$periode->id}/rouvrir")
            ->assertConflict()
            ->assertJsonPath('message', 'Impossible de rouvrir la période : des bulletins ont déjà été validés.');
    }

    public function test_l_annee_d_une_periode_ne_change_pas(): void
    {
        $periode = Periode::factory()->for($this->annee)->create();
        $autre = AnneeScolaire::factory()->debutant(2026)->create();

        $this->putJson("/api/periodes/{$periode->id}", $this->donnees(['annee_scolaire_id' => $autre->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('annee_scolaire_id');
    }

    public function test_filtre_par_annee(): void
    {
        Periode::factory()->for($this->annee)->create();
        Periode::factory()->create();

        $this->getJson("/api/periodes?annee_scolaire_id={$this->annee->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
