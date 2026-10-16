<?php

namespace Tests\Feature\Notes;

use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaisieNotesTest extends TestCase
{
    use ContexteNotes, RefreshDatabase;

    private Evaluation $evaluation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $this->evaluation = Evaluation::factory()->for($this->affectation)->for($this->trimestre)->create(['bareme' => 20]);
    }

    private function ligne(int $index, ?float $valeur, bool $absent = false): array
    {
        return ['eleve_id' => $this->eleves[$index]->id, 'valeur' => $valeur, 'absent' => $absent];
    }

    public function test_la_grille_liste_les_eleves_de_la_classe_par_ordre_alphabetique(): void
    {
        $this->connecterProf();
        Note::factory()->for($this->evaluation)->for($this->eleves[0])->create(['valeur' => 13.5]);

        $this->getJson("/api/evaluations/{$this->evaluation->id}/notes")
            ->assertOk()
            ->assertJsonPath('data.eleves.*.nom', ['Albert', 'Bernard', 'Durand'])
            ->assertJsonPath('data.eleves.1.valeur', 13.5)
            ->assertJsonPath('data.evaluation.verrouillee', false)
            ->assertJsonPath('data.statistiques.saisies', 1);
    }

    public function test_un_eleve_transfere_avec_une_note_reste_dans_la_grille(): void
    {
        $this->connecterProf();
        Note::factory()->for($this->evaluation)->for($this->eleves[2])->create(['valeur' => 9]);
        Inscription::where('eleve_id', $this->eleves[2]->id)->update(['statut' => 'transfere']);

        $this->getJson("/api/evaluations/{$this->evaluation->id}/notes")
            ->assertJsonCount(3, 'data.eleves')
            ->assertJsonPath('data.eleves.2.inscrit', false);
    }

    public function test_enregistrement_en_lot_creation_modification_absence_et_effacement(): void
    {
        $this->connecterProf();
        Note::factory()->for($this->evaluation)->for($this->eleves[2])->create(['valeur' => 5]);

        $this->putJson("/api/evaluations/{$this->evaluation->id}/notes", ['notes' => [
            $this->ligne(0, 14.25),
            $this->ligne(1, 18, absent: true),  // absent : la valeur est ignorée
            $this->ligne(2, null),              // effacée
        ]])
            ->assertOk()
            ->assertJsonPath('data.statistiques', ['saisies' => 2, 'absents' => 1, 'moyenne' => 14.25, 'min' => 14.25, 'max' => 14.25]);

        $this->assertEquals(14.25, Note::where('eleve_id', $this->eleves[0]->id)->value('valeur'));
        $this->assertDatabaseHas('notes', ['eleve_id' => $this->eleves[1]->id, 'absent' => true, 'valeur' => null]);
        $this->assertDatabaseMissing('notes', ['eleve_id' => $this->eleves[2]->id]);
    }

    public function test_une_note_ne_depasse_pas_le_bareme(): void
    {
        $this->connecterProf();

        $this->putJson("/api/evaluations/{$this->evaluation->id}/notes", ['notes' => [
            $this->ligne(0, 12),
            $this->ligne(1, 21),
            $this->ligne(2, -1),
        ]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'notes.1.valeur' => ['La note ne peut pas dépasser le barème (20).'],
                'notes.2.valeur' => ['La note ne peut pas être négative.'],
            ]);

        $this->assertSame(0, Note::count()); // rien n'est enregistré si une ligne est invalide
    }

    public function test_un_eleve_hors_de_la_classe_est_refuse(): void
    {
        $this->connecterProf();
        $intrus = Inscription::factory()->create()->eleve;

        $this->putJson("/api/evaluations/{$this->evaluation->id}/notes", ['notes' => [
            $this->ligne(0, 12),
            ['eleve_id' => $intrus->id, 'valeur' => 15, 'absent' => false],
        ]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['notes.1.eleve_id' => ['Cet élève n\'appartient pas à la classe évaluée.']]);
    }

    public function test_la_saisie_est_bloquee_quand_la_periode_est_cloturee(): void
    {
        $this->connecterProf();
        $this->trimestre->forceFill(['cloturee' => true])->save();

        $this->getJson("/api/evaluations/{$this->evaluation->id}/notes")
            ->assertOk()
            ->assertJsonPath('data.evaluation.verrouillee', true);

        $this->putJson("/api/evaluations/{$this->evaluation->id}/notes", ['notes' => [$this->ligne(0, 12)]])
            ->assertConflict()
            ->assertJsonPath('message', 'La période « Trimestre 1 » est clôturée : les notes ne sont plus modifiables.');
    }

    /** Règle du cahier des charges : jamais les notes d'une classe où le professeur n'est pas affecté. */
    public function test_un_professeur_ne_voit_ni_ne_modifie_les_notes_d_une_autre_classe(): void
    {
        $autre = Evaluation::factory()->for($this->affectationDUnCollegue())->for($this->trimestre)->create();
        $this->connecterProf();

        $this->getJson("/api/evaluations/{$autre->id}")->assertForbidden();
        $this->getJson("/api/evaluations/{$autre->id}/notes")->assertForbidden();
        $this->putJson("/api/evaluations/{$autre->id}/notes", ['notes' => []])->assertForbidden();
        $this->putJson("/api/evaluations/{$autre->id}", [])->assertForbidden();
        $this->deleteJson("/api/evaluations/{$autre->id}")->assertForbidden();
        $this->getJson("/api/evaluations?affectation_id={$autre->affectation_id}")->assertOk()->assertJsonPath('meta.total', 0);
    }
}
