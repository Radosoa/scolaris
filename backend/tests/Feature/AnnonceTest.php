<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\Annonce;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnonceTest extends TestCase
{
    use RefreshDatabase;

    private Classe $sixiemeA;

    private Classe $sixiemeB;

    private User $redacteur;

    protected function setUp(): void
    {
        parent::setUp();

        $annee = AnneeScolaire::factory()->debutant(2026)->active()->create();
        $this->sixiemeA = Classe::factory()->for($annee)->create(['nom' => '6e A']);
        $this->sixiemeB = Classe::factory()->for($annee)->create(['nom' => '6e B']);
        $this->redacteur = User::factory()->create(['name' => 'Sophie Bernard'])->assignRole(Role::Secretariat->value);

        foreach ([
            ['Rentrée', 'tous', null, '-2 days'],
            ['Conseil pédagogique', 'professeurs', null, '-1 day'],
            ['Réunion parents-professeurs', 'parents', null, '-3 hours'],
            ['Sortie au musée', 'classe', $this->sixiemeA->id, '-1 hour'],
            ['Photo de classe', 'classe', $this->sixiemeB->id, '-1 hour'],
            ['Vacances (programmée)', 'tous', null, '+3 days'],
        ] as [$titre, $cible, $classeId, $quand]) {
            $annonce = new Annonce(['titre' => $titre, 'contenu' => "Texte : {$titre}", 'cible' => $cible, 'classe_id' => $classeId, 'date_publication' => now()->modify($quand)]);
            $annonce->auteur()->associate($this->redacteur)->save();
        }
    }

    /** @return list<string> */
    private function titresVisibles(): array
    {
        return collect($this->getJson('/api/annonces?per_page=50')->assertOk()->json('data'))->pluck('titre')->all();
    }

    public function test_chacun_ne_voit_que_les_annonces_qui_le_concernent(): void
    {
        // Professeur de la 6e A
        $prof = Enseignant::factory()->create();
        Affectation::factory()->for($prof)->for($this->sixiemeA)->create();
        $this->actingAs($prof->user, 'sanctum');
        $this->assertSame(['Sortie au musée', 'Conseil pédagogique', 'Rentrée'], $this->titresVisibles());

        // Parent d'un élève de 6e B
        $eleveB = Inscription::factory()->for($this->sixiemeB)->create()->eleve;
        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $parent->id])->eleves()->attach($eleveB->id, ['lien' => 'mere']);
        $this->actingAs($parent, 'sanctum');
        $this->assertSame(['Photo de classe', 'Réunion parents-professeurs', 'Rentrée'], $this->titresVisibles());

        // Élève de 6e A
        $user = User::factory()->create()->assignRole(Role::Eleve->value);
        Inscription::factory()->for($this->sixiemeA)->for(Eleve::factory()->state(['user_id' => $user->id]))->create();
        $this->actingAs($user, 'sanctum');
        $this->assertSame(['Sortie au musée', 'Rentrée'], $this->titresVisibles());

        // Une annonce qui ne le concerne pas : 404, son existence n'est pas révélée
        $this->getJson('/api/annonces/'.Annonce::where('titre', 'Photo de classe')->value('id'))->assertNotFound();
        $this->getJson('/api/annonces/'.Annonce::where('titre', 'Vacances (programmée)')->value('id'))->assertNotFound();
        $this->getJson('/api/annonces/'.Annonce::where('titre', 'Rentrée')->value('id'))->assertOk()->assertJsonPath('data.auteur', 'Sophie Bernard');
    }

    public function test_les_redacteurs_voient_tout_y_compris_les_annonces_programmees(): void
    {
        $this->actingAs($this->redacteur, 'sanctum');

        $annonces = $this->getJson('/api/annonces?per_page=50')->assertOk()->json('data');
        $this->assertCount(6, $annonces);
        $this->assertSame('Vacances (programmée)', $annonces[0]['titre']);
        $this->assertFalse($annonces[0]['publiee']);
        $this->assertTrue($annonces[0]['modifiable']);
    }

    public function test_publier_une_annonce_ciblee(): void
    {
        $this->actingAs($this->redacteur, 'sanctum');

        $this->postJson('/api/annonces', ['titre' => 'Sortie', 'contenu' => 'Prévoir un pique-nique.', 'cible' => 'classe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classe_id' => 'Choisissez la classe concernée.']);

        $this->postJson('/api/annonces', ['titre' => 'Sortie', 'contenu' => 'Prévoir un pique-nique.', 'cible' => 'classe', 'classe_id' => $this->sixiemeA->id])
            ->assertCreated()
            ->assertJsonPath('data.cible_libelle', 'Une classe')
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.publiee', true);

        // La classe est ignorée pour une autre cible
        $this->postJson('/api/annonces', ['titre' => 'Info', 'contenu' => 'Pour tous', 'cible' => 'tous', 'classe_id' => $this->sixiemeA->id])
            ->assertCreated()
            ->assertJsonPath('data.classe', null);

        $this->postJson('/api/annonces', ['titre' => '', 'contenu' => '', 'cible' => 'eleves'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['titre', 'contenu', 'cible']);
    }

    public function test_seul_l_auteur_ou_la_direction_modifie_une_annonce(): void
    {
        $annonce = Annonce::where('titre', 'Rentrée')->firstOrFail();
        $donnees = ['titre' => 'Rentrée (mise à jour)', 'contenu' => 'Nouveau texte', 'cible' => 'tous', 'date_publication' => $annonce->date_publication->toIso8601String()];

        $this->connecter(Role::Secretariat);
        $this->putJson("/api/annonces/{$annonce->id}", $donnees)->assertForbidden();
        $this->deleteJson("/api/annonces/{$annonce->id}")->assertForbidden();

        $this->actingAs($this->redacteur, 'sanctum');
        $this->putJson("/api/annonces/{$annonce->id}", $donnees)->assertOk()->assertJsonPath('data.titre', 'Rentrée (mise à jour)');

        $this->connecter(Role::Admin);
        $this->deleteJson("/api/annonces/{$annonce->id}")->assertNoContent();
    }

    public function test_professeurs_eleves_et_parents_ne_publient_pas(): void
    {
        foreach ([Role::Professeur, Role::Eleve, Role::Parent] as $role) {
            $this->connecter($role);
            $this->postJson('/api/annonces', ['titre' => 'X', 'contenu' => 'Y', 'cible' => 'tous'])->assertForbidden();
        }
    }
}
