<?php

namespace Tests\Feature\VieScolaire;

use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Creneau;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\Matiere;

/**
 * Contexte commun : année 2025-2026 active, 6e A (trois élèves) et 6e B (un élève).
 * « Notre » professeur (Julien Moreau) enseigne les maths dans les deux classes,
 * Isabelle Lambert le français en 6e A.
 */
trait ContexteVieScolaire
{
    protected AnneeScolaire $annee;

    protected Classe $classeA;

    protected Classe $classeB;

    protected Enseignant $prof;

    protected Enseignant $collegue;

    protected Affectation $mathsA;

    protected Affectation $mathsB;

    protected Affectation $francaisA;

    /** @var list<Eleve> élèves de 6e A : Albert, Bernard, Durand */
    protected array $eleves;

    protected Eleve $eleveB;

    /** Un lundi de l'année scolaire (passé). */
    protected const LUNDI = '2025-09-08';

    protected function preparerContexte(): void
    {
        $this->annee = AnneeScolaire::factory()->debutant(2025)->active()->create();
        $this->classeA = Classe::factory()->for($this->annee)->create(['nom' => '6e A']);
        $this->classeB = Classe::factory()->for($this->annee)->create(['nom' => '6e B', 'niveau_id' => $this->classeA->niveau_id]);
        $this->prof = Enseignant::factory()->create(['prenom' => 'Julien', 'nom' => 'Moreau']);
        $this->collegue = Enseignant::factory()->create(['prenom' => 'Isabelle', 'nom' => 'Lambert']);

        $maths = Matiere::factory()->create(['nom' => 'Mathématiques', 'code' => 'MATH']);
        $francais = Matiere::factory()->create(['nom' => 'Français', 'code' => 'FR']);
        $this->mathsA = Affectation::factory()->for($this->prof)->for($maths)->for($this->classeA)->create();
        $this->mathsB = Affectation::factory()->for($this->prof)->for($maths)->for($this->classeB)->create();
        $this->francaisA = Affectation::factory()->for($this->collegue)->for($francais)->for($this->classeA)->create();

        $this->eleves = collect(['Albert', 'Bernard', 'Durand'])
            ->map(fn (string $nom) => Inscription::factory()->for($this->classeA)->for(Eleve::factory()->state(['nom' => $nom]))->create()->eleve)
            ->all();
        $this->eleveB = Inscription::factory()->for($this->classeB)->create()->eleve;
    }

    protected function creneau(Affectation $affectation, int $jour = 1, string $debut = '08:00', string $fin = '09:00', ?string $salle = null): Creneau
    {
        return Creneau::create(['affectation_id' => $affectation->id, 'jour' => $jour, 'heure_debut' => $debut, 'heure_fin' => $fin, 'salle' => $salle]);
    }
}
