<?php

namespace Tests\Feature\Personnes;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connecter(Role::Secretariat);
        $this->annee = AnneeScolaire::factory()->active()->create();
    }

    private function classe(int $capacite = 30, string $nom = '6e A'): Classe
    {
        return Classe::factory()->for($this->annee)->create(['capacite' => $capacite, 'nom' => $nom]);
    }

    private function remplir(Classe $classe, int $nombre): void
    {
        Inscription::factory()->count($nombre)->for($classe)->create();
    }

    public function test_inscription_dans_une_classe_de_l_annee_active(): void
    {
        $classe = $this->classe();
        $eleve = Eleve::factory()->create();

        $this->postJson('/api/inscriptions', ['eleve_id' => $eleve->id, 'classe_id' => $classe->id])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'inscrit')
            ->assertJsonPath('data.classe.nom', '6e A')
            ->assertJsonPath('data.annee_scolaire_id', $this->annee->id);
    }

    public function test_une_classe_complete_refuse_l_inscription(): void
    {
        $classe = $this->classe(capacite: 2);
        $this->remplir($classe, 2);

        $this->postJson('/api/inscriptions', ['eleve_id' => Eleve::factory()->create()->id, 'classe_id' => $classe->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.classe_id.0', 'La classe 6e A est complète (2/2).');
    }

    public function test_les_abandons_liberent_une_place(): void
    {
        $classe = $this->classe(capacite: 1);
        Inscription::factory()->for($classe)->create(['statut' => 'abandon']);

        $this->postJson('/api/inscriptions', ['eleve_id' => Eleve::factory()->create()->id, 'classe_id' => $classe->id])
            ->assertCreated();
    }

    public function test_un_eleve_n_est_inscrit_qu_une_fois_par_annee(): void
    {
        $inscription = Inscription::factory()->for($this->classe())->create();

        $this->postJson('/api/inscriptions', ['eleve_id' => $inscription->eleve_id, 'classe_id' => $this->classe(nom: '6e B')->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.eleve_id.0', "Cet élève est déjà inscrit en 6e A pour {$this->annee->libelle}.");
    }

    public function test_pas_d_inscription_hors_annee_active(): void
    {
        $ancienne = Classe::factory()->create();

        $this->postJson('/api/inscriptions', ['eleve_id' => Eleve::factory()->create()->id, 'classe_id' => $ancienne->id])
            ->assertConflict();
    }

    public function test_changer_de_classe_verifie_la_place_disponible(): void
    {
        $inscription = Inscription::factory()->for($this->classe())->create();
        $pleine = $this->classe(capacite: 1, nom: '6e B');
        $this->remplir($pleine, 1);
        $libre = $this->classe(nom: '6e C');

        $this->putJson("/api/inscriptions/{$inscription->id}", ['classe_id' => $pleine->id])->assertUnprocessable();
        $this->putJson("/api/inscriptions/{$inscription->id}", ['classe_id' => $libre->id])
            ->assertOk()
            ->assertJsonPath('data.classe.nom', '6e C');
    }

    public function test_changer_de_classe_reste_dans_la_meme_annee(): void
    {
        $inscription = Inscription::factory()->for($this->classe())->create();

        $this->putJson("/api/inscriptions/{$inscription->id}", ['classe_id' => Classe::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('classe_id');
    }

    public function test_revenir_au_statut_inscrit_exige_une_place(): void
    {
        $classe = $this->classe(capacite: 1);
        $partie = Inscription::factory()->for($classe)->create(['statut' => 'abandon']);
        $this->remplir($classe, 1);

        $this->putJson("/api/inscriptions/{$partie->id}", ['statut' => 'inscrit'])->assertUnprocessable();
        $this->putJson("/api/inscriptions/{$partie->id}", ['statut' => 'transfere'])
            ->assertOk()
            ->assertJsonPath('data.statut_libelle', 'Transféré');
    }

    public function test_un_eleve_deja_inscrit_garde_sa_place_quand_son_statut_ne_change_pas(): void
    {
        $classe = $this->classe(capacite: 1);
        $inscription = Inscription::factory()->for($classe)->create();

        $this->putJson("/api/inscriptions/{$inscription->id}", ['statut' => 'inscrit', 'classe_id' => $classe->id])->assertOk();
    }

    public function test_liste_de_l_annee_active_filtree_par_classe_et_recherche(): void
    {
        $a = $this->classe();
        $b = $this->classe(nom: '6e B');
        Inscription::factory()->for($a)->for(Eleve::factory()->state(['nom' => 'Bernard']))->create();
        Inscription::factory()->for($a)->for(Eleve::factory()->state(['nom' => 'Albert']))->create();
        Inscription::factory()->for($b)->create();
        Inscription::factory()->create(); // autre année

        $this->getJson('/api/inscriptions')->assertJsonPath('meta.total', 3);
        $this->getJson("/api/inscriptions?classe_id={$a->id}")
            ->assertJsonPath('data.*.eleve.nom_complet', fn ($noms) => str_ends_with($noms[0], 'Albert') && str_ends_with($noms[1], 'Bernard'));
        $this->getJson('/api/inscriptions?q=bernard')->assertJsonPath('meta.total', 1);
    }

    public function test_le_professeur_ne_gere_pas_les_inscriptions(): void
    {
        $this->connecter(Role::Professeur);

        $this->getJson('/api/inscriptions')->assertForbidden();
        $this->postJson('/api/inscriptions', [])->assertForbidden();
    }
}
