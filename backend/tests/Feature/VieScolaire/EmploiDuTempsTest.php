<?php

namespace Tests\Feature\VieScolaire;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploiDuTempsTest extends TestCase
{
    use ContexteVieScolaire, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
    }

    /** @return array<string, mixed> */
    private function donnees(Affectation $affectation, int $jour = 1, string $debut = '08:00', string $fin = '09:00', ?string $salle = null): array
    {
        return ['affectation_id' => $affectation->id, 'jour' => $jour, 'heure_debut' => $debut, 'heure_fin' => $fin, 'salle' => $salle];
    }

    // ---------------------------------------------------------------- Création

    public function test_la_direction_cree_modifie_et_supprime_un_creneau(): void
    {
        $this->connecter(Role::Admin);

        $id = $this->postJson('/api/creneaux', $this->donnees($this->mathsA, 2, '10:00', '11:30', '  Salle   12 '))
            ->assertCreated()
            ->assertJsonPath('data.jour_libelle', 'Mardi')
            ->assertJsonPath('data.heure_debut', '10:00')
            ->assertJsonPath('data.heure_fin', '11:30')
            ->assertJsonPath('data.salle', 'Salle 12')
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.enseignant.nom_complet', 'Julien Moreau')
            ->json('data.id');

        // Se déplacer sur sa propre plage n'est pas un conflit avec soi-même
        $this->putJson("/api/creneaux/{$id}", $this->donnees($this->mathsA, 2, '10:30', '11:30', 'Salle 12'))
            ->assertOk()
            ->assertJsonPath('data.heure_debut', '10:30');

        $this->deleteJson("/api/creneaux/{$id}")->assertNoContent();
        $this->assertDatabaseCount('creneaux', 0);
    }

    public function test_les_horaires_sont_valides(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson('/api/creneaux', $this->donnees($this->mathsA, 7, '10:00', '09:00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'jour' => 'Choisissez un jour du lundi au samedi.',
                'heure_fin' => 'L\'heure de fin doit suivre l\'heure de début.',
            ]);
        $this->postJson('/api/creneaux', $this->donnees($this->mathsA, 1, '06:00', '20:00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['heure_debut', 'heure_fin']);
    }

    public function test_seule_la_direction_modifie_l_emploi_du_temps(): void
    {
        $creneau = $this->creneau($this->mathsA);

        foreach ([Role::Secretariat, Role::Professeur, Role::Eleve, Role::Parent] as $role) {
            $this->connecter($role);
            $this->postJson('/api/creneaux', $this->donnees($this->mathsA, 3))->assertForbidden();
            $this->putJson("/api/creneaux/{$creneau->id}", $this->donnees($this->mathsA, 3))->assertForbidden();
            $this->deleteJson("/api/creneaux/{$creneau->id}")->assertForbidden();
        }
    }

    // ---------------------------------------------------------------- Conflits

    public function test_un_professeur_ne_peut_pas_etre_dans_deux_classes_en_meme_temps(): void
    {
        $this->creneau($this->mathsA, 1, '08:00', '10:00');
        $this->connecter(Role::Admin);

        // Même professeur, autre classe, 9 h – 10 h : chevauchement partiel
        $this->postJson('/api/creneaux', $this->donnees($this->mathsB, 1, '09:00', '10:00'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.conflits.0', 'Conflit de professeur : Julien Moreau a déjà cours en 6e A (Mathématiques) le lundi de 08:00 à 10:00.');
    }

    public function test_une_classe_ne_peut_pas_avoir_deux_cours_en_meme_temps(): void
    {
        $this->creneau($this->mathsA, 4, '14:00', '15:00');
        $this->connecter(Role::Admin);

        $this->postJson('/api/creneaux', $this->donnees($this->francaisA, 4, '13:30', '14:30'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.conflits.0', 'Conflit de classe : la 6e A a déjà Mathématiques avec Julien Moreau le jeudi de 14:00 à 15:00.');
    }

    public function test_une_salle_ne_peut_pas_accueillir_deux_cours_en_meme_temps(): void
    {
        $this->creneau($this->mathsB, 5, '08:00', '09:00', 'Salle 12');
        $this->connecter(Role::Admin);

        // Comparaison insensible à la casse et aux espaces
        $this->postJson('/api/creneaux', $this->donnees($this->francaisA, 5, '08:30', '09:30', ' salle 12'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.conflits.0', 'Conflit de salle : la salle Salle 12 est occupée par la 6e B (Mathématiques) le vendredi de 08:00 à 09:00.');
    }

    public function test_tous_les_conflits_sont_signales_en_une_fois(): void
    {
        $this->creneau($this->mathsA, 1, '08:00', '09:00', 'Salle 12');
        $this->connecter(Role::Admin);

        $this->postJson('/api/creneaux', $this->donnees($this->mathsB, 1, '08:00', '09:00', 'Salle 12'))
            ->assertUnprocessable()
            ->assertJsonCount(2, 'errors.conflits');
    }

    public function test_des_creneaux_qui_se_suivent_ou_un_autre_jour_ne_sont_pas_en_conflit(): void
    {
        $this->creneau($this->mathsA, 1, '08:00', '09:00', 'Salle 12');
        $this->connecter(Role::Admin);

        $this->postJson('/api/creneaux', $this->donnees($this->mathsB, 1, '09:00', '10:00', 'Salle 12'))->assertCreated();
        $this->postJson('/api/creneaux', $this->donnees($this->mathsB, 2, '08:00', '09:00', 'Salle 12'))->assertCreated();
        // Sans salle renseignée, pas de conflit de salle (la 6e A est libre à 9 h)
        $this->postJson('/api/creneaux', $this->donnees($this->francaisA, 1, '09:00', '10:00'))->assertCreated();
    }

    public function test_les_creneaux_d_une_autre_annee_ne_genent_pas(): void
    {
        $ancienne = AnneeScolaire::factory()->debutant(2024)->create();
        $ancienneClasse = Classe::factory()->for($ancienne)->create();
        $this->creneau(Affectation::factory()->for($this->prof)->for($ancienneClasse)->create(), 1, '08:00', '09:00', 'Salle 12');
        $this->connecter(Role::Admin);

        $this->postJson('/api/creneaux', $this->donnees($this->mathsA, 1, '08:00', '09:00', 'Salle 12'))->assertCreated();
    }

    // ------------------------------------------------------------ Consultation

    public function test_emploi_du_temps_d_une_classe_et_d_un_professeur(): void
    {
        $this->creneau($this->mathsA, 2, '10:00', '11:00');
        $this->creneau($this->mathsA, 1, '09:00', '10:00');
        $this->creneau($this->francaisA, 1, '08:00', '09:00');
        $this->creneau($this->mathsB, 3, '08:00', '09:00');
        $this->connecter(Role::Secretariat);

        $this->getJson("/api/emploi-du-temps?classe_id={$this->classeA->id}")
            ->assertOk()
            ->assertJsonPath('meta.type', 'classe')
            ->assertJsonPath('meta.libelle', '6e A')
            ->assertJsonCount(3, 'data')
            // Triés par jour puis par heure
            ->assertJsonPath('data.0.matiere.nom', 'Français')
            ->assertJsonPath('data.1.heure_debut', '09:00')
            ->assertJsonPath('data.2.jour', 2);

        $this->getJson("/api/emploi-du-temps?enseignant_id={$this->prof->id}")
            ->assertOk()
            ->assertJsonPath('meta.libelle', 'Julien Moreau')
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/emploi-du-temps')->assertUnprocessable();
    }

    public function test_le_professeur_voit_le_sien_et_celui_de_ses_classes_seulement(): void
    {
        $this->creneau($this->mathsA);
        $this->actingAs($this->prof->user, 'sanctum');

        $this->getJson('/api/emploi-du-temps')->assertOk()->assertJsonPath('meta.id', $this->prof->id);
        $this->getJson("/api/emploi-du-temps?classe_id={$this->classeA->id}")->assertOk();
        $this->getJson("/api/emploi-du-temps?enseignant_id={$this->collegue->id}")->assertForbidden();

        $autreClasse = Classe::factory()->for($this->annee)->create();
        $this->getJson("/api/emploi-du-temps?classe_id={$autreClasse->id}")->assertForbidden();
    }

    public function test_l_eleve_voit_celui_de_sa_classe_et_le_parent_celui_de_ses_enfants(): void
    {
        $this->creneau($this->mathsA);

        $eleve = User::factory()->create()->assignRole(Role::Eleve->value);
        $this->eleves[0]->user()->associate($eleve)->save();
        $this->actingAs($eleve, 'sanctum');
        $this->getJson('/api/emploi-du-temps')->assertOk()->assertJsonPath('meta.libelle', '6e A')->assertJsonCount(1, 'data');
        $this->getJson("/api/emploi-du-temps?classe_id={$this->classeB->id}")->assertForbidden();
        $this->getJson("/api/emploi-du-temps?enseignant_id={$this->prof->id}")->assertForbidden();

        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $parent->id])->eleves()->attach($this->eleveB->id, ['lien' => 'pere']);
        $this->actingAs($parent, 'sanctum');
        $this->getJson("/api/emploi-du-temps?classe_id={$this->classeB->id}")->assertOk();
        $this->getJson("/api/emploi-du-temps?classe_id={$this->classeA->id}")->assertForbidden();
    }
}
