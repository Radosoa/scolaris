<?php

namespace Tests\Feature\Structure;

use App\Enums\Role;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Matiere;
use App\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatiereNiveauCoefficientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connecter(Role::Admin);
    }

    public function test_le_code_matiere_est_mis_en_majuscules_et_unique(): void
    {
        $this->postJson('/api/matieres', ['nom' => 'Mathématiques', 'code' => ' math '])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MATH');

        $this->postJson('/api/matieres', ['nom' => 'Maths bis', 'code' => 'Math'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_le_code_matiere_n_accepte_pas_les_caracteres_speciaux(): void
    {
        $this->postJson('/api/matieres', ['nom' => 'Histoire', 'code' => 'H&G'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Le code ne peut contenir que des lettres, des chiffres et des tirets.');
    }

    public function test_recherche_de_matiere_par_nom_ou_code(): void
    {
        Matiere::factory()->create(['nom' => 'Français', 'code' => 'FR']);
        Matiere::factory()->create(['nom' => 'Physique-Chimie', 'code' => 'PC']);

        $this->getJson('/api/matieres?q=fr')->assertJsonPath('data.*.code', ['FR']);
        $this->getJson('/api/matieres?q=chimie')->assertJsonPath('data.*.code', ['PC']);
    }

    public function test_crud_niveau_avec_ordre_unique(): void
    {
        $id = $this->postJson('/api/niveaux', ['libelle' => '6e', 'ordre' => 1])->assertCreated()->json('data.id');

        $this->postJson('/api/niveaux', ['libelle' => '5e', 'ordre' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ordre');

        $this->putJson("/api/niveaux/{$id}", ['libelle' => 'Sixième', 'ordre' => 1])
            ->assertOk()
            ->assertJsonPath('data.libelle', 'Sixième');

        $this->deleteJson("/api/niveaux/{$id}")->assertNoContent();
    }

    public function test_un_niveau_avec_des_classes_ne_peut_pas_etre_supprime(): void
    {
        $classe = Classe::factory()->create();

        $this->deleteJson("/api/niveaux/{$classe->niveau_id}")
            ->assertConflict()
            ->assertJsonPath('message', 'Suppression impossible : cet élément est utilisé par d\'autres données.');
    }

    public function test_la_grille_liste_toutes_les_matieres_avec_leur_coefficient(): void
    {
        $niveau = Niveau::factory()->create();
        $maths = Matiere::factory()->create(['nom' => 'Mathématiques']);
        $anglais = Matiere::factory()->create(['nom' => 'Anglais']);
        Coefficient::factory()->create(['niveau_id' => $niveau->id, 'matiere_id' => $maths->id, 'valeur' => 4]);

        $this->getJson("/api/niveaux/{$niveau->id}/coefficients")
            ->assertOk()
            ->assertJsonPath('data', [
                ['matiere_id' => $anglais->id, 'nom' => 'Anglais', 'code' => $anglais->code, 'valeur' => null],
                ['matiere_id' => $maths->id, 'nom' => 'Mathématiques', 'code' => $maths->code, 'valeur' => 4],
            ]);
    }

    public function test_la_synchronisation_cree_modifie_et_supprime_les_coefficients(): void
    {
        $niveau = Niveau::factory()->create();
        [$maths, $francais, $eps] = Matiere::factory()->count(3)->create();
        Coefficient::factory()->create(['niveau_id' => $niveau->id, 'matiere_id' => $maths->id, 'valeur' => 2]);
        Coefficient::factory()->create(['niveau_id' => $niveau->id, 'matiere_id' => $eps->id, 'valeur' => 1]);

        $this->putJson("/api/niveaux/{$niveau->id}/coefficients", ['coefficients' => [
            ['matiere_id' => $maths->id, 'valeur' => 4],      // modifié
            ['matiere_id' => $francais->id, 'valeur' => 3.5], // créé
            ['matiere_id' => $eps->id, 'valeur' => null],     // supprimé
        ]])->assertOk();

        $this->assertEquals(
            [$maths->id => 4.0, $francais->id => 3.5],
            $niveau->coefficients()->pluck('valeur', 'matiere_id')->map(fn ($v) => (float) $v)->all(),
        );
    }

    public function test_les_coefficients_sont_valides(): void
    {
        $niveau = Niveau::factory()->create();
        $matiere = Matiere::factory()->create();

        $this->putJson("/api/niveaux/{$niveau->id}/coefficients", ['coefficients' => [
            ['matiere_id' => $matiere->id, 'valeur' => 25],
        ]])->assertUnprocessable()->assertJsonValidationErrors('coefficients.0.valeur');

        $this->putJson("/api/niveaux/{$niveau->id}/coefficients", ['coefficients' => [
            ['matiere_id' => $matiere->id, 'valeur' => 2],
            ['matiere_id' => $matiere->id, 'valeur' => 3],
        ]])->assertUnprocessable()->assertJsonValidationErrors('coefficients.0.matiere_id');
    }

    public function test_le_coefficient_est_propre_au_niveau(): void
    {
        $matiere = Matiere::factory()->create();
        $sixieme = Niveau::factory()->create();
        $troisieme = Niveau::factory()->create();

        $this->putJson("/api/niveaux/{$sixieme->id}/coefficients", ['coefficients' => [['matiere_id' => $matiere->id, 'valeur' => 2]]]);
        $this->putJson("/api/niveaux/{$troisieme->id}/coefficients", ['coefficients' => [['matiere_id' => $matiere->id, 'valeur' => 4]]]);

        $this->getJson("/api/niveaux/{$sixieme->id}/coefficients")->assertJsonPath('data.0.valeur', 2);
        $this->getJson("/api/niveaux/{$troisieme->id}/coefficients")->assertJsonPath('data.0.valeur', 4);
    }

    public function test_le_secretariat_ne_modifie_pas_les_coefficients(): void
    {
        $niveau = Niveau::factory()->create();
        $this->connecter(Role::Secretariat);

        $this->getJson("/api/niveaux/{$niveau->id}/coefficients")->assertOk();
        $this->putJson("/api/niveaux/{$niveau->id}/coefficients", ['coefficients' => []])->assertForbidden();
    }
}
