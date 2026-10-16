<?php

namespace Tests\Feature\Notes;

use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Periode;

/**
 * Contexte commun : année active 2025-2026, trimestre 1 ouvert, une classe de 6e A
 * avec trois élèves, une affectation de mathématiques pour « notre » professeur.
 */
trait ContexteNotes
{
    protected AnneeScolaire $annee;

    protected Periode $trimestre;

    protected Classe $classe;

    protected Affectation $affectation;

    protected Enseignant $prof;

    /** @var list<Eleve> */
    protected array $eleves;

    protected function preparerContexte(): void
    {
        $this->annee = AnneeScolaire::factory()->debutant(2025)->active()->create();
        $this->trimestre = Periode::factory()->for($this->annee)->create([
            'libelle' => 'Trimestre 1', 'date_debut' => '2025-09-01', 'date_fin' => '2025-11-30',
        ]);
        $this->classe = Classe::factory()->for($this->annee)->create(['nom' => '6e A']);
        $maths = Matiere::factory()->create(['nom' => 'Mathématiques', 'code' => 'MATH']);
        Coefficient::factory()->create(['niveau_id' => $this->classe->niveau_id, 'matiere_id' => $maths->id, 'valeur' => 4]);

        $this->prof = Enseignant::factory()->create();
        $this->affectation = Affectation::factory()->for($this->prof)->for($maths)->for($this->classe)->create();

        $this->eleves = collect(['Bernard', 'Albert', 'Durand'])
            ->map(fn (string $nom) => Inscription::factory()->for($this->classe)
                ->for(Eleve::factory()->state(['nom' => $nom]))->create()->eleve)
            ->all();
    }

    /** Une autre classe, enseignée par un autre professeur. */
    protected function affectationDUnCollegue(): Affectation
    {
        $classe = Classe::factory()->for($this->annee)->create(['nom' => '6e B', 'niveau_id' => $this->classe->niveau_id]);
        Inscription::factory()->for($classe)->create();

        return Affectation::factory()->for($classe)->create(['matiere_id' => $this->affectation->matiere_id]);
    }

    protected function connecterProf(): void
    {
        $this->actingAs($this->prof->user, 'sanctum');
    }
}
