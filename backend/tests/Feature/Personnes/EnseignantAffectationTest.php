<?php

namespace Tests\Feature\Personnes;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnseignantAffectationTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->annee = AnneeScolaire::factory()->active()->create();
    }

    /** Classe de l'année active où la matière est enseignée (coefficient défini). */
    private function classeAvecMatiere(Matiere $matiere, string $nom = '6e A'): Classe
    {
        $classe = Classe::factory()->for($this->annee)->create(['nom' => $nom]);
        Coefficient::factory()->create(['niveau_id' => $classe->niveau_id, 'matiere_id' => $matiere->id, 'valeur' => 2]);

        return $classe;
    }

    public function test_creer_un_enseignant_cree_son_compte_professeur(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson('/api/enseignants', [
            'nom' => 'Moreau', 'prenom' => 'Julien', 'specialite' => 'Mathématiques',
            'email' => 'j.moreau@scolaris.test', 'password' => 'secret123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nom_complet', 'Julien Moreau')
            ->assertJsonPath('data.email', 'j.moreau@scolaris.test');

        $this->postJson('/api/auth/login', ['email' => 'j.moreau@scolaris.test', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('user.roles', ['professeur']);
    }

    public function test_l_email_de_l_enseignant_est_unique_et_le_mot_de_passe_robuste(): void
    {
        $admin = $this->connecter(Role::Admin);

        $this->postJson('/api/enseignants', [
            'nom' => 'Moreau', 'prenom' => 'Julien', 'email' => $admin->email, 'password' => 'court',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Cette adresse e-mail est déjà utilisée par un autre compte.')
            ->assertJsonValidationErrors('password');
    }

    public function test_modifier_l_enseignant_met_a_jour_son_compte(): void
    {
        $this->connecter(Role::Admin);
        $enseignant = Enseignant::factory()->create();

        $this->putJson("/api/enseignants/{$enseignant->id}", ['nom' => 'Petit', 'prenom' => 'Anne', 'email' => 'anne@scolaris.test'])
            ->assertOk();

        $this->assertSame('anne@scolaris.test', $enseignant->user->fresh()->email);
        $this->assertSame('Anne Petit', $enseignant->user->fresh()->name);
    }

    public function test_archiver_un_enseignant_affecte_cette_annee_est_refuse(): void
    {
        $this->connecter(Role::Admin);
        $matiere = Matiere::factory()->create();
        $affectation = Affectation::factory()->for($this->classeAvecMatiere($matiere))->for($matiere)->create();

        $this->deleteJson("/api/enseignants/{$affectation->enseignant_id}")->assertConflict();

        $affectation->delete();
        $this->deleteJson("/api/enseignants/{$affectation->enseignant_id}")->assertNoContent();
        $this->assertFalse($affectation->enseignant->user->fresh()->actif);
    }

    public function test_le_secretariat_consulte_les_enseignants_sans_les_gerer(): void
    {
        $this->connecter(Role::Secretariat);
        Enseignant::factory()->create();

        $this->getJson('/api/enseignants')->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson('/api/enseignants', [])->assertForbidden();
    }

    public function test_affecter_un_enseignant_a_une_matiere_enseignee_dans_la_classe(): void
    {
        $this->connecter(Role::Admin);
        $matiere = Matiere::factory()->create(['nom' => 'Mathématiques']);
        $classe = $this->classeAvecMatiere($matiere);
        $enseignant = Enseignant::factory()->create();

        $this->postJson('/api/affectations', ['enseignant_id' => $enseignant->id, 'matiere_id' => $matiere->id, 'classe_id' => $classe->id])
            ->assertCreated()
            ->assertJsonPath('data.matiere.nom', 'Mathématiques')
            ->assertJsonPath('data.classe.nom', '6e A');
    }

    public function test_une_matiere_sans_coefficient_au_niveau_ne_peut_pas_etre_affectee(): void
    {
        $this->connecter(Role::Admin);
        $classe = Classe::factory()->for($this->annee)->create();
        $classe->niveau->update(['libelle' => '6e']);
        $matiere = Matiere::factory()->create(['nom' => 'Physique-Chimie']);

        $this->postJson('/api/affectations', [
            'enseignant_id' => Enseignant::factory()->create()->id, 'matiere_id' => $matiere->id, 'classe_id' => $classe->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.matiere_id.0', "Physique-Chimie n'est pas enseignée en 6e : définissez d'abord son coefficient.");
    }

    public function test_une_seule_affectation_par_matiere_et_par_classe(): void
    {
        $this->connecter(Role::Admin);
        $matiere = Matiere::factory()->create();
        $affectation = Affectation::factory()->for($this->classeAvecMatiere($matiere))->for($matiere)->create();

        $this->postJson('/api/affectations', [
            'enseignant_id' => Enseignant::factory()->create()->id, 'matiere_id' => $matiere->id, 'classe_id' => $affectation->classe_id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.matiere_id.0', 'Un enseignant est déjà affecté à cette matière dans cette classe.');

        // Remplacer l'enseignant de l'affectation existante reste possible
        $this->putJson("/api/affectations/{$affectation->id}", [
            'enseignant_id' => Enseignant::factory()->create()->id, 'matiere_id' => $matiere->id, 'classe_id' => $affectation->classe_id,
        ])->assertOk();
    }

    public function test_le_professeur_ne_voit_que_ses_affectations(): void
    {
        $matiere = Matiere::factory()->create();
        $moi = Enseignant::factory()->create();
        Affectation::factory()->for($moi)->for($matiere)->for($this->classeAvecMatiere($matiere))->create();
        Affectation::factory()->for($matiere)->for($this->classeAvecMatiere($matiere, '6e B'))->create();
        $this->actingAs($moi->user, 'sanctum');

        $this->getJson('/api/affectations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.enseignant.id', $moi->id);
        $this->postJson('/api/affectations', [])->assertForbidden();
    }

    public function test_une_affectation_avec_evaluations_ne_peut_pas_etre_supprimee(): void
    {
        $this->connecter(Role::Admin);
        $matiere = Matiere::factory()->create();
        $affectation = Affectation::factory()->for($matiere)->for($this->classeAvecMatiere($matiere))->create();
        DB::table('evaluations')->insert([
            'affectation_id' => $affectation->id,
            'periode_id' => Periode::factory()->for($this->annee)->create()->id,
            'type' => 'devoir', 'titre' => 'DS 1', 'date' => now()->toDateString(), 'bareme' => 20,
        ]);

        $this->deleteJson("/api/affectations/{$affectation->id}")->assertConflict();
    }
}
