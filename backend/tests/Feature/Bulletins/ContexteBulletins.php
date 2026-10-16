<?php

namespace Tests\Feature\Bulletins;

use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Coefficient;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;

/**
 * Contexte commun : année 2025-2026, trimestre 1 clôturé, une classe de 6e A
 * avec trois élèves (Albert, Bernard, Durand), trois matières :
 * mathématiques (coef. 4, « notre » professeur), français (coef. 3), anglais (coef. 2).
 */
trait ContexteBulletins
{
    protected AnneeScolaire $annee;

    protected Periode $trimestre;

    protected Classe $classe;

    protected Enseignant $prof;

    /** @var array<string, Affectation> maths, francais, anglais */
    protected array $aff;

    /** @var list<Eleve> */
    protected array $eleves;

    protected function preparerContexte(): void
    {
        $this->annee = AnneeScolaire::factory()->debutant(2025)->active()->create();
        $this->trimestre = Periode::factory()->for($this->annee)->cloturee()->create([
            'libelle' => 'Trimestre 1', 'date_debut' => '2025-09-01', 'date_fin' => '2025-11-30',
        ]);
        $this->classe = Classe::factory()->for($this->annee)->create(['nom' => '6e A']);
        $this->prof = Enseignant::factory()->create(['nom' => 'Moreau', 'prenom' => 'Julien']);

        $this->aff = [];
        foreach (['maths' => ['Mathématiques', 'MATH', 4], 'francais' => ['Français', 'FR', 3], 'anglais' => ['Anglais', 'ANG', 2]] as $cle => [$nom, $code, $coef]) {
            $matiere = Matiere::factory()->create(['nom' => $nom, 'code' => $code]);
            Coefficient::factory()->create(['niveau_id' => $this->classe->niveau_id, 'matiere_id' => $matiere->id, 'valeur' => $coef]);
            $this->aff[$cle] = Affectation::factory()
                ->for($cle === 'maths' ? $this->prof : Enseignant::factory())
                ->for($matiere)->for($this->classe)->create();
        }

        $this->eleves = collect(['Albert', 'Bernard', 'Durand'])
            ->map(fn (string $nom) => $this->inscrire($this->classe, $nom))
            ->all();
    }

    protected function inscrire(Classe $classe, string $nom): Eleve
    {
        return Inscription::factory()->for($classe)->for(Eleve::factory()->state(['nom' => $nom]))->create()->eleve;
    }

    /**
     * Crée une évaluation et ses notes : [index élève => valeur], null = absent.
     *
     * @param  array<int, float|null>  $notes
     */
    protected function evaluer(Affectation $affectation, array $notes, string $type = 'devoir', float $bareme = 20, ?array $eleves = null): Evaluation
    {
        $evaluation = Evaluation::factory()->for($affectation)->for($this->trimestre)->create(['type' => $type, 'bareme' => $bareme]);
        $eleves ??= $this->eleves;

        foreach ($notes as $index => $valeur) {
            Note::factory()->for($evaluation)->for($eleves[$index])->create(['valeur' => $valeur, 'absent' => $valeur === null]);
        }

        return $evaluation;
    }
}
