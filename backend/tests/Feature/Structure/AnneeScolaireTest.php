<?php

namespace Tests\Feature\Structure;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnneeScolaireTest extends TestCase
{
    use RefreshDatabase;

    private function donnees(array $surcharges = []): array
    {
        return [
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-07-15',
            ...$surcharges,
        ];
    }

    public function test_l_admin_cree_une_annee_scolaire(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson('/api/annees-scolaires', $this->donnees())
            ->assertCreated()
            ->assertJsonPath('data.libelle', '2025-2026')
            ->assertJsonPath('data.date_debut', '2025-09-01')
            ->assertJsonPath('data.active', false);
    }

    public function test_creer_une_annee_active_desactive_les_autres(): void
    {
        $this->connecter(Role::Admin);
        $ancienne = AnneeScolaire::factory()->debutant(2024)->active()->create();

        $this->postJson('/api/annees-scolaires', $this->donnees(['active' => true]))
            ->assertCreated()
            ->assertJsonPath('data.active', true);

        $this->assertFalse($ancienne->fresh()->active);
        $this->assertSame(1, AnneeScolaire::where('active', true)->count());
    }

    public function test_activer_une_annee_garantit_une_seule_annee_active(): void
    {
        $this->connecter(Role::Admin);
        $a = AnneeScolaire::factory()->debutant(2024)->active()->create();
        $b = AnneeScolaire::factory()->debutant(2025)->create();

        $this->postJson("/api/annees-scolaires/{$b->id}/activer")
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->assertFalse($a->fresh()->active);
        $this->assertSame([$b->id], AnneeScolaire::where('active', true)->pluck('id')->all());
    }

    public function test_le_libelle_doit_couvrir_deux_annees_consecutives(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson('/api/annees-scolaires', $this->donnees(['libelle' => '2025-2027']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('libelle');

        $this->postJson('/api/annees-scolaires', $this->donnees(['libelle' => '25-26']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.libelle.0', 'Le libellé doit être au format AAAA-AAAA (ex. 2025-2026).');
    }

    public function test_la_date_de_fin_doit_suivre_la_date_de_debut(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson('/api/annees-scolaires', $this->donnees(['date_fin' => '2025-08-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_fin');
    }

    public function test_le_libelle_est_unique(): void
    {
        $this->connecter(Role::Admin);
        AnneeScolaire::factory()->debutant(2025)->create();

        $this->postJson('/api/annees-scolaires', $this->donnees())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('libelle');
    }

    public function test_la_modification_ne_peut_pas_exclure_des_periodes_existantes(): void
    {
        $this->connecter(Role::Admin);
        $annee = AnneeScolaire::factory()->debutant(2025)->create();
        Periode::factory()->for($annee)->create(['date_debut' => '2025-09-01', 'date_fin' => '2025-11-30']);

        $this->putJson("/api/annees-scolaires/{$annee->id}", $this->donnees(['date_debut' => '2025-10-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_debut');
    }

    public function test_l_annee_active_ne_peut_pas_etre_supprimee(): void
    {
        $this->connecter(Role::Admin);
        $annee = AnneeScolaire::factory()->active()->create();

        $this->deleteJson("/api/annees-scolaires/{$annee->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Impossible de supprimer l\'année scolaire active.');
    }

    public function test_une_annee_avec_des_classes_ne_peut_pas_etre_supprimee(): void
    {
        $this->connecter(Role::Admin);
        $classe = Classe::factory()->create();

        $this->deleteJson("/api/annees-scolaires/{$classe->annee_scolaire_id}")->assertConflict();
        $this->assertModelExists($classe->anneeScolaire);
    }

    public function test_une_annee_sans_dependance_est_supprimee(): void
    {
        $this->connecter(Role::Admin);
        $annee = AnneeScolaire::factory()->create();

        $this->deleteJson("/api/annees-scolaires/{$annee->id}")->assertNoContent();
        $this->assertModelMissing($annee);
    }

    public function test_la_liste_est_paginee_et_filtrable(): void
    {
        $this->connecter(Role::Admin);
        foreach ([2021, 2022, 2023, 2024] as $debut) {
            AnneeScolaire::factory()->debutant($debut)->create();
        }

        $this->getJson('/api/annees-scolaires?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.libelle', '2024-2025');

        $this->getJson('/api/annees-scolaires?q=2022')
            ->assertOk()
            ->assertJsonPath('data.*.libelle', ['2022-2023', '2021-2022']);

        $this->getJson('/api/annees-scolaires?q=2023-2024')
            ->assertOk()
            ->assertJsonPath('data.*.libelle', ['2023-2024']);
    }

    public function test_annee_active_renvoie_404_puis_l_annee_avec_ses_periodes(): void
    {
        $this->connecter(Role::Professeur);

        $this->getJson('/api/annee-active')
            ->assertNotFound()
            ->assertJsonPath('message', 'Aucune année scolaire active.');

        $annee = AnneeScolaire::factory()->active()->create();
        Periode::factory()->for($annee)->create();

        $this->getJson('/api/annee-active')
            ->assertOk()
            ->assertJsonPath('data.id', $annee->id)
            ->assertJsonCount(1, 'data.periodes');
    }

    public function test_le_secretariat_consulte_mais_ne_modifie_pas(): void
    {
        $this->connecter(Role::Secretariat);
        $annee = AnneeScolaire::factory()->create();

        $this->getJson('/api/annees-scolaires')->assertOk();
        $this->postJson('/api/annees-scolaires', $this->donnees())->assertForbidden();
        $this->postJson("/api/annees-scolaires/{$annee->id}/activer")->assertForbidden();
        $this->deleteJson("/api/annees-scolaires/{$annee->id}")->assertForbidden();
    }

    public function test_un_eleve_ou_un_parent_n_accede_pas_a_la_structure(): void
    {
        $this->connecter(Role::Eleve);
        $this->getJson('/api/annees-scolaires')->assertForbidden();

        $this->connecter(Role::Parent);
        $this->getJson('/api/classes')->assertForbidden();
    }

    public function test_un_utilisateur_non_autorise_recoit_403_avant_la_validation(): void
    {
        $this->connecter(Role::Professeur);

        $this->postJson('/api/annees-scolaires', [])->assertForbidden();
    }
}
