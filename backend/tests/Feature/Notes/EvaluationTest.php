<?php

namespace Tests\Feature\Notes;

use App\Enums\Role;
use App\Models\Evaluation;
use App\Models\Note;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use ContexteNotes, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
    }

    private function donnees(array $surcharges = []): array
    {
        return [
            'affectation_id' => $this->affectation->id,
            'periode_id' => $this->trimestre->id,
            'type' => 'devoir',
            'titre' => 'Fractions',
            'date' => '2025-10-06',
            'bareme' => 20,
            ...$surcharges,
        ];
    }

    public function test_le_professeur_cree_une_evaluation_pour_son_affectation(): void
    {
        $this->connecterProf();

        $this->postJson('/api/evaluations', $this->donnees(['type' => 'composition', 'bareme' => 40]))
            ->assertCreated()
            ->assertJsonPath('data.type_libelle', 'Composition')
            ->assertJsonPath('data.bareme', 40)
            ->assertJsonPath('data.verrouillee', false)
            ->assertJsonPath('data.affectation.classe.nom', '6e A');
    }

    public function test_le_professeur_ne_cree_rien_pour_la_classe_d_un_collegue(): void
    {
        $this->connecterProf();
        $autre = $this->affectationDUnCollegue();

        $this->postJson('/api/evaluations', $this->donnees(['affectation_id' => $autre->id]))->assertForbidden();
    }

    public function test_le_secretariat_ne_cree_pas_d_evaluation(): void
    {
        $this->connecter(Role::Secretariat);

        $this->postJson('/api/evaluations', $this->donnees())->assertForbidden();
        $this->getJson('/api/evaluations')->assertForbidden();
    }

    public function test_la_direction_peut_creer_une_evaluation(): void
    {
        $this->connecter(Role::Admin);

        $this->postJson('/api/evaluations', $this->donnees())->assertCreated();
    }

    public function test_la_date_doit_etre_dans_la_periode(): void
    {
        $this->connecterProf();

        $this->postJson('/api/evaluations', $this->donnees(['date' => '2025-12-15']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'La date doit être comprise dans la période (01/09/2025 → 30/11/2025).');
    }

    public function test_la_periode_doit_appartenir_a_l_annee_de_la_classe(): void
    {
        $this->connecterProf();
        $autreAnnee = Periode::factory()->create();

        $this->postJson('/api/evaluations', $this->donnees(['periode_id' => $autreAnnee->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('periode_id');
    }

    public function test_pas_d_evaluation_sur_une_periode_cloturee(): void
    {
        $this->connecterProf();
        $this->trimestre->forceFill(['cloturee' => true])->save();

        $this->postJson('/api/evaluations', $this->donnees())->assertConflict();
    }

    public function test_une_evaluation_d_une_periode_cloturee_n_est_ni_modifiable_ni_supprimable(): void
    {
        $this->connecterProf();
        $evaluation = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create();
        $this->trimestre->forceFill(['cloturee' => true])->save();

        $this->putJson("/api/evaluations/{$evaluation->id}", collect($this->donnees())->except('affectation_id')->all())
            ->assertConflict();
        $this->deleteJson("/api/evaluations/{$evaluation->id}")->assertConflict();
    }

    public function test_le_bareme_ne_descend_pas_sous_une_note_deja_saisie(): void
    {
        $this->connecterProf();
        $evaluation = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create(['bareme' => 20]);
        Note::factory()->for($evaluation)->for($this->eleves[0])->create(['valeur' => 15.5]);

        $this->putJson("/api/evaluations/{$evaluation->id}", collect($this->donnees(['bareme' => 10]))->except('affectation_id')->all())
            ->assertUnprocessable()
            ->assertJsonPath('errors.bareme.0', 'Une note de 15,5 a déjà été saisie : le barème ne peut pas être inférieur.');

        $this->putJson("/api/evaluations/{$evaluation->id}", collect($this->donnees(['bareme' => 16]))->except('affectation_id')->all())
            ->assertOk();
    }

    public function test_supprimer_une_evaluation_supprime_ses_notes(): void
    {
        $this->connecterProf();
        $evaluation = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create();
        Note::factory()->for($evaluation)->for($this->eleves[0])->create();

        $this->deleteJson("/api/evaluations/{$evaluation->id}")->assertNoContent();
        $this->assertSame(0, Note::count());
    }

    public function test_la_liste_ne_contient_que_les_evaluations_du_professeur_avec_statistiques(): void
    {
        $mienne = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create();
        Note::factory()->for($mienne)->for($this->eleves[0])->create(['valeur' => 12]);
        Note::factory()->for($mienne)->for($this->eleves[1])->create(['valeur' => 16]);
        Note::factory()->for($mienne)->for($this->eleves[2])->create(['valeur' => null, 'absent' => true]);
        Evaluation::factory()->for($this->affectationDUnCollegue())->for($this->trimestre)->create();
        $this->connecterProf();

        $this->getJson('/api/evaluations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.notes_count', 3)
            ->assertJsonPath('data.0.moyenne', 14);
    }
}
