<?php

namespace Tests\Feature\Notes;

use App\Enums\Role;
use App\Models\Evaluation;
use App\Models\Note;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleveNotesTest extends TestCase
{
    use ContexteNotes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $devoir = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create(['titre' => 'Fractions', 'date' => '2025-10-06']);
        $compo = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create(['titre' => 'Composition', 'type' => 'composition', 'bareme' => 40, 'date' => '2025-11-20']);
        Note::factory()->for($devoir)->for($this->eleves[0])->create(['valeur' => 15]);
        Note::factory()->for($compo)->for($this->eleves[0])->create(['valeur' => null, 'absent' => true]);
        Note::factory()->for($devoir)->for($this->eleves[1])->create(['valeur' => 4]);
    }

    public function test_l_eleve_consulte_ses_propres_notes_par_matiere(): void
    {
        $user = User::factory()->create()->assignRole(Role::Eleve->value);
        $this->eleves[0]->user()->associate($user)->save();
        $this->actingAs($user, 'sanctum');

        $this->getJson("/api/eleves/{$this->eleves[0]->id}/notes?periode_id={$this->trimestre->id}")
            ->assertOk()
            ->assertJsonPath('data.periode_id', $this->trimestre->id)
            ->assertJsonPath('data.matieres.0.matiere.nom', 'Mathématiques')
            ->assertJsonPath('data.matieres.0.evaluations.0.valeur', 15)
            ->assertJsonPath('data.matieres.0.evaluations.1.absent', true)
            ->assertJsonPath('data.matieres.0.evaluations.1.bareme', 40);

        $this->getJson("/api/eleves/{$this->eleves[1]->id}/notes")->assertForbidden();
    }

    public function test_le_parent_consulte_les_notes_de_son_enfant_uniquement(): void
    {
        $user = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $user->id])->eleves()->attach($this->eleves[1]->id, ['lien' => 'pere']);
        $this->actingAs($user, 'sanctum');

        $this->getJson("/api/eleves/{$this->eleves[1]->id}/notes?periode_id={$this->trimestre->id}")
            ->assertOk()
            ->assertJsonPath('data.matieres.0.evaluations.0.valeur', 4);
        $this->getJson("/api/eleves/{$this->eleves[0]->id}/notes")->assertForbidden();
    }

    public function test_un_professeur_d_une_autre_classe_ne_consulte_pas_le_releve(): void
    {
        $collegue = $this->affectationDUnCollegue()->enseignant;
        $this->actingAs($collegue->user, 'sanctum');

        $this->getJson("/api/eleves/{$this->eleves[0]->id}/notes")->assertForbidden();
    }
}
