<?php

namespace Tests\Feature\Bulletins;

use App\Enums\Role;
use App\Jobs\GenererBulletinsClasse;
use App\Models\Bulletin;
use App\Models\Enseignant;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\Bulletins\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulletinsApiTest extends TestCase
{
    use ContexteBulletins, RefreshDatabase;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->preparerContexte();
        $this->evaluer($this->aff['maths'], [0 => 15, 1 => 11, 2 => 8]);
        $this->evaluer($this->aff['francais'], [0 => 13, 1 => 12, 2 => 10], 'composition');
        $this->url = "/api/classes/{$this->classe->id}/periodes/{$this->trimestre->id}/bulletins";
    }

    private function calculer(): void
    {
        app(BulletinService::class)->calculer($this->classe, $this->trimestre);
    }

    private function valider(): void
    {
        $this->calculer();
        app(BulletinService::class)->valider($this->classe, $this->trimestre, User::factory()->create());
    }

    private function bulletinDe(int $eleve): Bulletin
    {
        return Bulletin::whereHas('inscription', fn ($q) => $q->where('eleve_id', $this->eleves[$eleve]->id))->firstOrFail();
    }

    /** Compte parent rattaché à l'élève d'index donné. */
    private function connecterParentDe(int $eleve): void
    {
        $user = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $user->id])->eleves()->attach($this->eleves[$eleve]->id, ['lien' => 'mere']);
        $this->actingAs($user, 'sanctum');
    }

    private function connecterEleve(int $eleve): void
    {
        $user = User::factory()->create()->assignRole(Role::Eleve->value);
        $this->eleves[$eleve]->user()->associate($user)->save();
        $this->actingAs($user, 'sanctum');
    }

    // ----------------------------------------------------------------- Direction

    public function test_la_direction_calcule_previsualise_et_valide_les_bulletins_d_une_classe(): void
    {
        $this->connecter(Role::Admin);

        $this->getJson($this->url)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.etat', 'non_calcule');

        $this->postJson("{$this->url}/calculer")
            ->assertOk()
            ->assertJsonPath('meta.etat', 'brouillon')
            ->assertJsonPath('meta.nombre', 3)
            ->assertJsonCount(3, 'data')
            // Classés par rang : Albert (15 et 13 → 14,14), Bernard, Durand
            ->assertJsonPath('data.0.eleve.nom', 'Albert')
            ->assertJsonPath('data.0.rang', 1)
            ->assertJsonPath('data.0.moyenne', 14.14)
            ->assertJsonPath('data.0.mention', 'Tableau d\'honneur avec encouragements')
            ->assertJsonPath('data.2.eleve.nom', 'Durand')
            ->assertJsonPath('data.0.valide', false);

        $id = $this->bulletinDe(0)->id;
        $this->getJson("/api/bulletins/{$id}")
            ->assertOk()
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.ex_aequo', false)
            ->assertJsonPath('data.lignes.0.matiere.nom', 'Anglais')
            ->assertJsonPath('data.lignes.0.moyenne', null)
            ->assertJsonPath('data.lignes.2.matiere.nom', 'Mathématiques')
            ->assertJsonPath('data.lignes.2.enseignant', 'Julien Moreau')
            ->assertJsonPath('data.lignes.2.coefficient', 4);

        $this->putJson("/api/bulletins/{$id}/appreciation", ['appreciation_generale' => '  Très bon trimestre.  '])
            ->assertOk()
            ->assertJsonPath('data.appreciation_generale', 'Très bon trimestre.');

        $this->postJson("{$this->url}/valider")
            ->assertOk()
            ->assertJsonPath('meta.etat', 'valide')
            ->assertJsonPath('data.0.valide', true);

        // Figé : ni recalcul, ni nouvelle validation, ni appréciation, ni réouverture de la période
        $this->postJson("{$this->url}/calculer")->assertConflict();
        $this->postJson("{$this->url}/valider")->assertConflict();
        $this->putJson("/api/bulletins/{$id}/appreciation", ['appreciation_generale' => 'Modifiée'])->assertConflict();
        $this->postJson("/api/periodes/{$this->trimestre->id}/rouvrir")->assertConflict();
    }

    public function test_les_regles_metier_renvoient_un_conflit(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson("{$this->url}/valider")->assertConflict()->assertJsonPath('message', 'Calculez les bulletins avant de les valider.');
        $this->postJson("{$this->url}/pdf")->assertConflict();

        $this->trimestre->forceFill(['cloturee' => false])->save();
        $this->postJson("{$this->url}/calculer")
            ->assertConflict()
            ->assertJsonPath('message', 'La période « Trimestre 1 » n\'est pas clôturée : clôturez-la avant de générer les bulletins.');
    }

    public function test_seule_la_direction_gere_les_bulletins_d_une_classe(): void
    {
        foreach ([Role::Secretariat, Role::Professeur, Role::Parent, Role::Eleve] as $role) {
            $this->connecter($role);

            $this->getJson($this->url)->assertForbidden();
            $this->postJson("{$this->url}/calculer")->assertForbidden();
            $this->postJson("{$this->url}/valider")->assertForbidden();
            $this->postJson("{$this->url}/pdf")->assertForbidden();
        }

        $this->calculer();
        $this->connecter(Role::Secretariat);
        $this->putJson("/api/bulletins/{$this->bulletinDe(0)->id}/appreciation", ['appreciation_generale' => 'x'])->assertForbidden();
    }

    // ---------------------------------------------------------------------- PDF

    public function test_pdf_individuel_brouillon_puis_valide(): void
    {
        $this->calculer();
        $this->connecter(Role::Admin);
        $id = $this->bulletinDe(0)->id;

        $brouillon = $this->get("/api/bulletins/{$id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $brouillon->getContent());
        $this->assertNull($this->bulletinDe(0)->pdf_path, 'Un brouillon n\'est pas stocké');

        app(BulletinService::class)->valider($this->classe, $this->trimestre, User::first());
        $reponse = $this->get("/api/bulletins/{$id}/pdf")->assertOk();
        $this->assertStringContainsString('Bulletin_trimestre-1_albert_', $reponse->headers->get('content-disposition'));

        // Le PDF validé est conservé et resservi tel quel
        $chemin = $this->bulletinDe(0)->pdf_path;
        $this->assertNotNull($chemin);
        Storage::disk('local')->assertExists($chemin);
    }

    public function test_la_generation_en_lot_passe_par_un_job_de_queue(): void
    {
        $this->valider();
        $this->connecter(Role::Admin);
        Queue::fake();

        $this->postJson("{$this->url}/pdf")->assertAccepted();

        Queue::assertPushed(GenererBulletinsClasse::class, fn ($job) => $job->classeId === $this->classe->id && $job->periodeId === $this->trimestre->id);
        $this->getJson($this->url)->assertJsonPath('meta.pdf_en_cours', true);
    }

    public function test_le_job_genere_un_pdf_par_eleve_et_le_pdf_de_la_classe(): void
    {
        $this->valider();
        $this->connecter(Role::Admin);
        $this->get("{$this->url}/pdf")->assertNotFound();

        // QUEUE_CONNECTION=sync en test : le job s'exécute immédiatement
        $this->postJson("{$this->url}/pdf")->assertAccepted();

        $this->getJson($this->url)
            ->assertJsonPath('meta.pdf_generes', 3)
            ->assertJsonPath('meta.pdf_en_cours', false)
            ->assertJsonPath('meta.pdf_classe', true);
        foreach ([0, 1, 2] as $eleve) {
            Storage::disk('local')->assertExists($this->bulletinDe($eleve)->pdf_path);
        }

        $pdf = $this->get("{$this->url}/pdf")->assertOk();
        $this->assertStringContainsString('Bulletins_6e-a_trimestre-1.pdf', $pdf->headers->get('content-disposition'));
    }

    // ---------------------------------------------------------- Parents, élèves

    public function test_le_parent_ne_voit_que_les_bulletins_valides_de_son_enfant(): void
    {
        $this->calculer();
        $this->connecterParentDe(1);
        $bulletin = $this->bulletinDe(1);

        // Brouillon : invisible
        $this->getJson("/api/eleves/{$this->eleves[1]->id}/bulletins")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/bulletins/{$bulletin->id}")->assertForbidden();
        $this->get("/api/bulletins/{$bulletin->id}/pdf")->assertForbidden();

        app(BulletinService::class)->valider($this->classe, $this->trimestre, User::factory()->create());

        $this->getJson("/api/eleves/{$this->eleves[1]->id}/bulletins")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.periode.libelle', 'Trimestre 1')
            ->assertJsonPath('data.0.rang', 2);
        $this->getJson("/api/bulletins/{$bulletin->id}")->assertOk()->assertJsonPath('data.eleve.nom', 'Bernard');
        $this->get("/api/bulletins/{$bulletin->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        // Jamais ceux d'un autre enfant
        $autre = $this->bulletinDe(0);
        $this->getJson("/api/bulletins/{$autre->id}")->assertForbidden();
        $this->get("/api/bulletins/{$autre->id}/pdf")->assertForbidden();
        $this->getJson("/api/eleves/{$this->eleves[0]->id}/bulletins")->assertForbidden();
    }

    public function test_l_eleve_consulte_ses_bulletins_valides_uniquement(): void
    {
        $this->valider();
        $this->connecterEleve(2);

        $this->getJson("/api/eleves/{$this->eleves[2]->id}/bulletins")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/bulletins/{$this->bulletinDe(2)->id}")->assertOk();
        $this->getJson("/api/bulletins/{$this->bulletinDe(0)->id}")->assertForbidden();
        $this->putJson("/api/bulletins/{$this->bulletinDe(2)->id}/appreciation", ['appreciation_generale' => 'Moi'])->assertForbidden();
    }

    public function test_le_professeur_consulte_les_bulletins_valides_de_ses_classes_seulement(): void
    {
        $this->valider();

        $this->actingAs($this->prof->user, 'sanctum');
        $this->getJson("/api/bulletins/{$this->bulletinDe(0)->id}")->assertOk();

        $etranger = User::factory()->create()->assignRole(Role::Professeur->value);
        Enseignant::factory()->create(['user_id' => $etranger->id]);
        $this->actingAs($etranger, 'sanctum');
        $this->getJson("/api/bulletins/{$this->bulletinDe(0)->id}")->assertForbidden();
    }

    // -------------------------------------------------------------- Appréciations

    public function test_le_professeur_saisit_les_appreciations_de_sa_matiere(): void
    {
        $this->actingAs($this->prof->user, 'sanctum');
        $url = "/api/affectations/{$this->aff['maths']->id}/appreciations";

        $this->getJson("{$url}?periode_id={$this->trimestre->id}")
            ->assertOk()
            ->assertJsonPath('data.calcule', false)
            ->assertJsonCount(0, 'data.lignes');

        $this->calculer();
        $lignes = $this->getJson("{$url}?periode_id={$this->trimestre->id}")
            ->assertOk()
            ->assertJsonPath('data.calcule', true)
            ->assertJsonPath('data.verrouille', false)
            ->assertJsonPath('data.matiere.nom', 'Mathématiques')
            ->assertJsonPath('data.lignes.0.eleve.nom', 'Albert')
            ->assertJsonPath('data.lignes.0.moyenne', 15)
            ->assertJsonPath('data.lignes.0.rang', 1)
            ->json('data.lignes');

        $this->putJson($url, ['periode_id' => $this->trimestre->id, 'appreciations' => [
            ['ligne_id' => $lignes[0]['id'], 'appreciation' => 'Excellent travail.'],
            ['ligne_id' => $lignes[1]['id'], 'appreciation' => '   '],
        ]])
            ->assertOk()
            ->assertJsonPath('data.lignes.0.appreciation', 'Excellent travail.')
            ->assertJsonPath('data.lignes.1.appreciation', null);

        // La ligne d'une autre matière est refusée
        $francais = $this->bulletinDe(0)->lignes()->where('matiere_id', $this->aff['francais']->matiere_id)->first();
        $this->putJson($url, ['periode_id' => $this->trimestre->id, 'appreciations' => [
            ['ligne_id' => $francais->id, 'appreciation' => 'Intrusion'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('appreciations.0.ligne_id');
        $this->assertNull($francais->fresh()->appreciation);

        $this->putJson($url, ['periode_id' => $this->trimestre->id, 'appreciations' => [
            ['ligne_id' => $lignes[0]['id'], 'appreciation' => str_repeat('a', 301)],
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            'appreciations.0.appreciation' => 'L\'appréciation ne doit pas dépasser 300 caractères.',
        ]);

        app(BulletinService::class)->valider($this->classe, $this->trimestre, User::factory()->create());
        $this->getJson("{$url}?periode_id={$this->trimestre->id}")->assertJsonPath('data.verrouille', true);
        $this->putJson($url, ['periode_id' => $this->trimestre->id, 'appreciations' => [
            ['ligne_id' => $lignes[0]['id'], 'appreciation' => 'Trop tard'],
        ]])->assertConflict();
    }

    public function test_un_professeur_ne_saisit_jamais_d_appreciation_dans_la_matiere_d_un_collegue(): void
    {
        $this->calculer();
        $this->actingAs($this->prof->user, 'sanctum');
        $url = "/api/affectations/{$this->aff['francais']->id}/appreciations";

        $this->getJson("{$url}?periode_id={$this->trimestre->id}")->assertForbidden();
        $this->putJson($url, ['periode_id' => $this->trimestre->id, 'appreciations' => [
            ['ligne_id' => $this->bulletinDe(0)->lignes()->first()->id, 'appreciation' => 'Intrusion'],
        ]])->assertForbidden();

        foreach ([Role::Secretariat, Role::Parent, Role::Eleve] as $role) {
            $this->connecter($role);
            $this->getJson("{$url}?periode_id={$this->trimestre->id}")->assertForbidden();
        }
    }
}
