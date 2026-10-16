<?php

namespace Tests\Feature\Personnes;

use App\Enums\Role;
use App\Models\Eleve;
use App\Models\Tuteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuteurTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_d_un_parent(): void
    {
        $this->connecter(Role::Secretariat);

        $id = $this->postJson('/api/tuteurs', ['nom' => 'Petit', 'prenom' => 'Nathalie', 'telephone' => '06 12 34 56 78', 'email' => 'n.petit@exemple.fr'])
            ->assertCreated()
            ->assertJsonPath('data.nom_complet', 'Nathalie Petit')
            ->json('data.id');

        $this->putJson("/api/tuteurs/{$id}", ['nom' => 'Petit', 'prenom' => 'Nathalie', 'telephone' => '+33 6 12 34 56 78'])
            ->assertOk()
            ->assertJsonPath('data.email', null);

        $this->deleteJson("/api/tuteurs/{$id}")->assertNoContent();
        $this->assertSoftDeleted('tuteurs', ['id' => $id]);
    }

    public function test_le_telephone_est_valide(): void
    {
        $this->connecter(Role::Secretariat);

        $this->postJson('/api/tuteurs', ['nom' => 'Petit', 'prenom' => 'N', 'telephone' => 'appelez-moi'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.telephone.0', 'Le numéro de téléphone n\'est pas valide.');
    }

    public function test_la_liste_affiche_les_enfants_et_se_filtre(): void
    {
        $this->connecter(Role::Secretariat);
        $tuteur = Tuteur::factory()->create(['nom' => 'Rousseau']);
        $tuteur->eleves()->attach(Eleve::factory()->count(2)->create(), ['lien' => 'pere']);
        Tuteur::factory()->create(['nom' => 'Fontaine']);

        $this->getJson('/api/tuteurs?q=rouss')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.eleves_count', 2)
            ->assertJsonPath('data.0.eleves.0.lien_libelle', 'Père');
    }

    public function test_creation_du_compte_parent(): void
    {
        $this->connecter(Role::Secretariat);
        $tuteur = Tuteur::factory()->create();

        $this->postJson("/api/tuteurs/{$tuteur->id}/compte", ['email' => 'parent@exemple.fr', 'password' => 'motdepasse1'])
            ->assertOk()
            ->assertJsonPath('data.compte.email', 'parent@exemple.fr');

        $this->assertTrue($tuteur->fresh()->user->hasRole(Role::Parent->value));
    }

    public function test_archiver_un_parent_desactive_son_compte(): void
    {
        $this->connecter(Role::Secretariat);
        $tuteur = Tuteur::factory()->create();
        $this->postJson("/api/tuteurs/{$tuteur->id}/compte", ['email' => 'parent@exemple.fr', 'password' => 'motdepasse1']);

        $this->deleteJson("/api/tuteurs/{$tuteur->id}")->assertNoContent();

        $this->postJson('/api/auth/login', ['email' => 'parent@exemple.fr', 'password' => 'motdepasse1'])
            ->assertUnprocessable();
    }
}
