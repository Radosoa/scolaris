<?php

namespace Tests\Feature\Personnes;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Tuteur;
use App\Services\EleveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EleveTest extends TestCase
{
    use RefreshDatabase;

    private function donnees(array $surcharges = []): array
    {
        return [
            'nom' => 'Durand',
            'prenom' => 'Léa',
            'sexe' => 'F',
            'date_naissance' => '2013-04-12',
            'lieu_naissance' => 'Nantes',
            ...$surcharges,
        ];
    }

    public function test_le_matricule_est_genere_sequentiellement_a_partir_de_l_annee_active(): void
    {
        $this->connecter(Role::Secretariat);
        AnneeScolaire::factory()->debutant(2026)->active()->create();

        $this->postJson('/api/eleves', $this->donnees())->assertCreated()->assertJsonPath('data.matricule', '2026-0001');
        $this->postJson('/api/eleves', $this->donnees(['prenom' => 'Tom']))->assertJsonPath('data.matricule', '2026-0002');
    }

    public function test_la_sequence_tient_compte_des_dossiers_archives(): void
    {
        $this->connecter(Role::Secretariat);
        AnneeScolaire::factory()->debutant(2026)->active()->create();
        Eleve::factory()->create(['matricule' => '2026-0041'])->delete();

        $this->postJson('/api/eleves', $this->donnees())->assertJsonPath('data.matricule', '2026-0042');
    }

    public function test_creation_avec_parents(): void
    {
        $this->connecter(Role::Secretariat);
        [$mere, $pere] = Tuteur::factory()->count(2)->create();

        $this->postJson('/api/eleves', $this->donnees(['tuteurs' => [
            ['tuteur_id' => $mere->id, 'lien' => 'mere'],
            ['tuteur_id' => $pere->id, 'lien' => 'pere'],
        ]]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.tuteurs')
            ->assertJsonPath('data.tuteurs.0.lien_libelle', 'Mère');
    }

    public function test_la_modification_sans_tuteurs_conserve_les_liens(): void
    {
        $this->connecter(Role::Secretariat);
        $eleve = Eleve::factory()->create();
        $eleve->tuteurs()->attach(Tuteur::factory()->create(), ['lien' => 'pere']);

        $this->putJson("/api/eleves/{$eleve->id}", $this->donnees())->assertOk()->assertJsonCount(1, 'data.tuteurs');
        $this->putJson("/api/eleves/{$eleve->id}", $this->donnees(['tuteurs' => []]))->assertOk()->assertJsonCount(0, 'data.tuteurs');
    }

    public function test_validation_des_champs(): void
    {
        $this->connecter(Role::Secretariat);

        $this->postJson('/api/eleves', $this->donnees(['sexe' => 'X', 'date_naissance' => now()->addDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sexe', 'date_naissance']);

        $this->postJson('/api/eleves', $this->donnees(['tuteurs' => [['tuteur_id' => 999, 'lien' => 'oncle']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tuteurs.0.tuteur_id', 'tuteurs.0.lien']);
    }

    public function test_recherche_par_nom_prenom_ou_matricule(): void
    {
        $this->connecter(Role::Secretariat);
        Eleve::factory()->create(['nom' => 'Lefebvre', 'prenom' => 'Chloé', 'matricule' => '2026-0007']);
        Eleve::factory()->create(['nom' => 'Garnier', 'prenom' => 'Noah']);

        $this->getJson('/api/eleves?q=chlo')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/eleves?q=2026-0007')->assertJsonPath('data.0.nom', 'Lefebvre');
        $this->getJson('/api/eleves?q=Noah%20Garnier')->assertJsonPath('meta.total', 1);
    }

    public function test_un_eleve_inscrit_cette_annee_ne_peut_pas_etre_archive(): void
    {
        $this->connecter(Role::Secretariat);
        $inscription = Inscription::factory()->create();
        $inscription->anneeScolaire->forceFill(['active' => true])->save();

        $this->deleteJson("/api/eleves/{$inscription->eleve_id}")->assertConflict();

        $inscription->update(['statut' => 'abandon']);
        $this->deleteJson("/api/eleves/{$inscription->eleve_id}")->assertNoContent();
        $this->assertSoftDeleted('eleves', ['id' => $inscription->eleve_id]);
    }

    public function test_photo_privee_servie_par_url_signee(): void
    {
        Storage::fake(EleveService::DISQUE_PHOTOS);
        $this->connecter(Role::Secretariat);
        $eleve = Eleve::factory()->create();

        $url = $this->postJson("/api/eleves/{$eleve->id}/photo", ['photo' => UploadedFile::fake()->image('photo.jpg', 300, 400)])
            ->assertOk()
            ->json('data.photo_url');

        $this->assertNotNull($url);
        Storage::disk(EleveService::DISQUE_PHOTOS)->assertExists($eleve->fresh()->photo_path);

        $this->get($url)->assertOk();
        // URL altérée ou non signée : refusée
        $this->get(preg_replace('/signature=[^&]+/', 'signature=falsifiee', $url))->assertForbidden();
        $this->get("/api/eleves/{$eleve->id}/photo")->assertForbidden();
    }

    public function test_remplacer_puis_supprimer_la_photo_efface_les_fichiers(): void
    {
        Storage::fake(EleveService::DISQUE_PHOTOS);
        $this->connecter(Role::Secretariat);
        $eleve = Eleve::factory()->create();

        $this->postJson("/api/eleves/{$eleve->id}/photo", ['photo' => UploadedFile::fake()->image('a.png')]);
        $premiere = $eleve->fresh()->photo_path;
        $this->postJson("/api/eleves/{$eleve->id}/photo", ['photo' => UploadedFile::fake()->image('b.png')]);

        Storage::disk(EleveService::DISQUE_PHOTOS)->assertMissing($premiere);

        $this->deleteJson("/api/eleves/{$eleve->id}/photo")->assertOk()->assertJsonPath('data.photo_url', null);
        $this->assertCount(0, Storage::disk(EleveService::DISQUE_PHOTOS)->allFiles());
    }

    public function test_la_photo_doit_etre_une_image_de_moins_de_2_mo(): void
    {
        $this->connecter(Role::Secretariat);
        $eleve = Eleve::factory()->create();

        $this->postJson("/api/eleves/{$eleve->id}/photo", ['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')])
            ->assertUnprocessable();
        $this->postJson("/api/eleves/{$eleve->id}/photo", ['photo' => UploadedFile::fake()->image('lourde.jpg')->size(3000)])
            ->assertUnprocessable();
    }

    public function test_creation_du_compte_eleve(): void
    {
        $this->connecter(Role::Secretariat);
        $eleve = Eleve::factory()->create();

        $this->postJson("/api/eleves/{$eleve->id}/compte", ['email' => 'lea@exemple.fr', 'password' => 'motdepasse1'])
            ->assertOk()
            ->assertJsonPath('data.compte.email', 'lea@exemple.fr');

        $this->assertTrue($eleve->fresh()->user->hasRole(Role::Eleve->value));
        $this->postJson("/api/eleves/{$eleve->id}/compte", ['email' => 'autre@exemple.fr', 'password' => 'motdepasse1'])
            ->assertConflict();

        // Le compte permet de se connecter
        $this->postJson('/api/auth/login', ['email' => 'lea@exemple.fr', 'password' => 'motdepasse1'])->assertOk();
    }
}
