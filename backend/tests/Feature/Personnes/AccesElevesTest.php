<?php

namespace Tests\Feature\Personnes;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règles de confidentialité du cahier des charges :
 * un professeur ne voit que ses classes, un parent que ses enfants, un élève que lui-même.
 */
class AccesElevesTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classeDuProf;

    private Classe $autreClasse;

    private Eleve $eleveDuProf;

    private Eleve $autreEleve;

    protected function setUp(): void
    {
        parent::setUp();

        $annee = AnneeScolaire::factory()->active()->create();
        $this->classeDuProf = Classe::factory()->for($annee)->create(['nom' => '6e A']);
        $this->autreClasse = Classe::factory()->for($annee)->create(['nom' => '6e B']);
        $this->eleveDuProf = Inscription::factory()->for($this->classeDuProf)->create()->eleve;
        $this->autreEleve = Inscription::factory()->for($this->autreClasse)->create()->eleve;
    }

    private function professeur(): User
    {
        $enseignant = Enseignant::factory()->create();
        Affectation::factory()->for($enseignant)->for($this->classeDuProf)->create();
        $this->actingAs($enseignant->user, 'sanctum');

        return $enseignant->user;
    }

    public function test_le_professeur_ne_liste_que_les_eleves_de_ses_classes(): void
    {
        $this->professeur();

        $this->getJson('/api/eleves')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->eleveDuProf->id);

        // Même en filtrant explicitement sur une autre classe
        $this->getJson("/api/eleves?classe_id={$this->autreClasse->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_le_professeur_ne_consulte_pas_un_eleve_hors_de_ses_classes(): void
    {
        $this->professeur();

        $this->getJson("/api/eleves/{$this->eleveDuProf->id}")->assertOk();
        $this->getJson("/api/eleves/{$this->autreEleve->id}")->assertForbidden();
    }

    public function test_le_professeur_ne_modifie_aucun_dossier_eleve(): void
    {
        $this->professeur();

        $this->putJson("/api/eleves/{$this->eleveDuProf->id}", [
            'nom' => 'Pirate', 'prenom' => 'Test', 'sexe' => 'M', 'date_naissance' => '2013-01-01',
        ])->assertForbidden();
        $this->postJson('/api/eleves', [])->assertForbidden();
    }

    public function test_un_professeur_sans_affectation_ne_voit_aucun_eleve(): void
    {
        $this->connecter(Role::Professeur);

        $this->getJson('/api/eleves')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_le_parent_ne_voit_que_ses_enfants(): void
    {
        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        $tuteur = Tuteur::factory()->create(['user_id' => $parent->id]);
        $tuteur->eleves()->attach($this->eleveDuProf->id, ['lien' => 'mere']);
        $this->actingAs($parent, 'sanctum');

        $this->getJson("/api/eleves/{$this->eleveDuProf->id}")->assertOk();
        $this->getJson("/api/eleves/{$this->autreEleve->id}")->assertForbidden();
        $this->getJson('/api/eleves')->assertForbidden();

        $this->getJson('/api/moi/enfants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->eleveDuProf->id)
            ->assertJsonPath('data.0.inscription_active.classe.nom', '6e A');
    }

    public function test_un_parent_ne_voit_pas_la_fiche_d_un_autre_parent(): void
    {
        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        $sienne = Tuteur::factory()->create(['user_id' => $parent->id]);
        $autre = Tuteur::factory()->create();
        $this->actingAs($parent, 'sanctum');

        $this->getJson("/api/tuteurs/{$sienne->id}")->assertOk();
        $this->getJson("/api/tuteurs/{$autre->id}")->assertForbidden();
        $this->getJson('/api/tuteurs')->assertForbidden();
    }

    public function test_l_eleve_ne_voit_que_son_propre_dossier(): void
    {
        $user = User::factory()->create()->assignRole(Role::Eleve->value);
        $this->eleveDuProf->user()->associate($user)->save();
        $this->actingAs($user, 'sanctum');

        $this->getJson("/api/eleves/{$this->eleveDuProf->id}")->assertOk();
        $this->getJson("/api/eleves/{$this->autreEleve->id}")->assertForbidden();
        $this->getJson('/api/eleves')->assertForbidden();
    }

    public function test_seul_un_parent_accede_a_mes_enfants(): void
    {
        $this->connecter(Role::Secretariat);

        $this->getJson('/api/moi/enfants')->assertForbidden();
    }

    public function test_le_secretariat_voit_tous_les_eleves(): void
    {
        $this->connecter(Role::Secretariat);

        $this->getJson('/api/eleves')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/eleves/{$this->autreEleve->id}")->assertOk();
    }
}
