<?php

namespace Tests\Feature\Structure;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClasseTest extends TestCase
{
    use RefreshDatabase;

    private function inscrire(Classe $classe, int $nombre): void
    {
        foreach (range(1, $nombre) as $i) {
            $eleveId = DB::table('eleves')->insertGetId([
                'matricule' => "E{$classe->id}-{$i}", 'nom' => 'Élève', 'prenom' => "N{$i}", 'sexe' => 'M', 'date_naissance' => '2012-01-01',
            ]);
            DB::table('inscriptions')->insert([
                'eleve_id' => $eleveId, 'classe_id' => $classe->id, 'annee_scolaire_id' => $classe->annee_scolaire_id, 'date_inscription' => '2025-09-01',
            ]);
        }
    }

    public function test_l_admin_cree_une_classe(): void
    {
        $this->connecter(Role::Admin);
        $niveau = Niveau::factory()->create(['libelle' => '6e']);
        $annee = AnneeScolaire::factory()->create();

        $this->postJson('/api/classes', [
            'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id, 'nom' => '6e A', 'capacite' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('data.nom', '6e A')
            ->assertJsonPath('data.effectif', 0)
            ->assertJsonPath('data.niveau.libelle', '6e')
            ->assertJsonPath('data.annee_scolaire.libelle', $annee->libelle);
    }

    public function test_le_nom_est_unique_par_annee_seulement(): void
    {
        $this->connecter(Role::Admin);
        $existante = Classe::factory()->create(['nom' => '6e A']);
        $autreAnnee = AnneeScolaire::factory()->create();
        $donnees = ['niveau_id' => $existante->niveau_id, 'nom' => '6e A', 'capacite' => 30];

        $this->postJson('/api/classes', [...$donnees, 'annee_scolaire_id' => $existante->annee_scolaire_id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nom.0', 'Une classe porte déjà ce nom pour cette année scolaire.');

        $this->postJson('/api/classes', [...$donnees, 'annee_scolaire_id' => $autreAnnee->id])->assertCreated();
    }

    public function test_la_capacite_ne_descend_pas_sous_l_effectif(): void
    {
        $this->connecter(Role::Admin);
        $classe = Classe::factory()->create(['capacite' => 30]);
        $this->inscrire($classe, 3);
        $donnees = $classe->only(['niveau_id', 'annee_scolaire_id', 'nom']);

        $this->putJson("/api/classes/{$classe->id}", [...$donnees, 'capacite' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('errors.capacite.0', "La capacité ne peut pas être inférieure à l'effectif actuel (3 élèves).");

        $this->putJson("/api/classes/{$classe->id}", [...$donnees, 'capacite' => 3])
            ->assertOk()
            ->assertJsonPath('data.effectif', 3);
    }

    public function test_une_classe_avec_eleves_ne_change_pas_d_annee(): void
    {
        $this->connecter(Role::Admin);
        $classe = Classe::factory()->create();
        $this->inscrire($classe, 1);
        $autre = AnneeScolaire::factory()->create();

        $this->putJson("/api/classes/{$classe->id}", [
            ...$classe->only(['niveau_id', 'nom', 'capacite']), 'annee_scolaire_id' => $autre->id,
        ])->assertConflict();
    }

    public function test_une_classe_avec_eleves_ne_peut_pas_etre_supprimee(): void
    {
        $this->connecter(Role::Admin);
        $classe = Classe::factory()->create();
        $this->inscrire($classe, 1);

        $this->deleteJson("/api/classes/{$classe->id}")->assertConflict();
    }

    public function test_liste_filtree_par_annee_et_niveau_triee_par_niveau(): void
    {
        $this->connecter(Role::Secretariat);
        $annee = AnneeScolaire::factory()->create();
        $sixieme = Niveau::factory()->create(['libelle' => '6e', 'ordre' => 1]);
        $cinquieme = Niveau::factory()->create(['libelle' => '5e', 'ordre' => 2]);
        Classe::factory()->for($annee)->for($cinquieme)->create(['nom' => '5e A']);
        Classe::factory()->for($annee)->for($sixieme)->create(['nom' => '6e B']);
        Classe::factory()->for($annee)->for($sixieme)->create(['nom' => '6e A']);
        Classe::factory()->create(); // autre année

        $this->getJson("/api/classes?annee_scolaire_id={$annee->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.*.nom', ['6e A', '6e B', '5e A']);

        $this->getJson("/api/classes?annee_scolaire_id={$annee->id}&niveau_id={$cinquieme->id}")
            ->assertJsonPath('data.*.nom', ['5e A']);

        $this->getJson('/api/classes?q=6e%20B')->assertJsonPath('data.*.nom', ['6e B']);
    }

    public function test_le_professeur_ne_peut_pas_creer_de_classe(): void
    {
        $this->connecter(Role::Professeur);

        $this->postJson('/api/classes', [])->assertForbidden();
    }
}
