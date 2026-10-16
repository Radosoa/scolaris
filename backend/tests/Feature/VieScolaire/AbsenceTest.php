<?php

namespace Tests\Feature\VieScolaire;

use App\Enums\Role;
use App\Models\Absence;
use App\Models\Creneau;
use App\Models\Tuteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use ContexteVieScolaire, RefreshDatabase;

    private Creneau $maths;

    private Creneau $francais;

    protected function setUp(): void
    {
        parent::setUp();

        $this->preparerContexte();
        $this->maths = $this->creneau($this->mathsA, 1, '08:00', '10:00');
        $this->francais = $this->creneau($this->francaisA, 1, '10:00', '11:00');
    }

    private function absence(int $eleve, Creneau $creneau, string $date = self::LUNDI, string $type = 'absence', bool $justifiee = false): Absence
    {
        return Absence::create([
            'eleve_id' => $this->eleves[$eleve]->id, 'creneau_id' => $creneau->id, 'date' => $date,
            'heure_debut' => $creneau->heure_debut,
            'heure_fin' => $type === 'retard' ? '08:20' : $creneau->heure_fin,
            'type' => $type, 'justifiee' => $justifiee,
        ]);
    }

    public function test_la_vie_scolaire_liste_et_filtre_les_absences(): void
    {
        $this->absence(0, $this->maths);
        $this->absence(0, $this->francais);
        $this->absence(1, $this->maths, '2025-09-15', 'retard');
        $this->absence(2, $this->maths, '2025-09-22', justifiee: true);
        $this->connecter(Role::Secretariat);

        $this->getJson('/api/absences')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.a_justifier', 3)
            ->assertJsonPath('data.0.date', '2025-09-22')
            ->assertJsonPath('data.0.classe.nom', '6e A')
            ->assertJsonPath('data.0.matiere', 'Mathématiques')
            ->assertJsonPath('data.0.duree_heures', 2);

        $this->getJson('/api/absences?statut=a_justifier&type=absence')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/absences?type=retard')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.minutes_retard', 20)
            ->assertJsonPath('data.0.duree_heures', null);
        $this->getJson('/api/absences?q=Albert')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/absences?du=2025-09-10&au=2025-09-20')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/absences?classe_id={$this->classeB->id}")->assertJsonPath('meta.total', 0);
    }

    public function test_justifier_une_absence_ou_toute_la_journee(): void
    {
        $maths = $this->absence(0, $this->maths);
        $francais = $this->absence(0, $this->francais);
        $autreJour = $this->absence(0, $this->maths, '2025-09-15');
        $this->connecter(Role::Secretariat);

        $this->putJson("/api/absences/{$maths->id}/justification", ['justifiee' => true, 'motif' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motif' => 'Indiquez le motif de l\'absence.']);

        $this->putJson("/api/absences/{$maths->id}/justification", ['justifiee' => true, 'motif' => ' Certificat médical ', 'toute_la_journee' => true])
            ->assertOk()
            ->assertJsonPath('data.justifiee', true)
            ->assertJsonPath('data.motif', 'Certificat médical')
            ->assertJsonPath('meta.mises_a_jour', 2);
        $this->assertTrue($francais->fresh()->justifiee);
        $this->assertFalse($autreJour->fresh()->justifiee);

        // Retrait de la justification : le motif est effacé
        $this->putJson("/api/absences/{$francais->id}/justification", ['justifiee' => false, 'motif' => 'ignoré'])
            ->assertOk()
            ->assertJsonPath('data.motif', null)
            ->assertJsonPath('meta.mises_a_jour', 1);
        $this->assertTrue($maths->fresh()->justifiee);
    }

    public function test_professeurs_eleves_et_parents_ne_gerent_pas_les_absences(): void
    {
        $absence = $this->absence(0, $this->maths);

        foreach ([Role::Professeur, Role::Eleve, Role::Parent] as $role) {
            $this->connecter($role);
            $this->getJson('/api/absences')->assertForbidden();
            $this->putJson("/api/absences/{$absence->id}/justification", ['justifiee' => true, 'motif' => 'x'])->assertForbidden();
        }
    }

    public function test_le_parent_consulte_les_absences_de_son_enfant_uniquement(): void
    {
        $this->absence(1, $this->maths, justifiee: true);
        $this->absence(1, $this->francais);
        $this->absence(1, $this->maths, '2025-09-15', 'retard');
        $this->absence(0, $this->maths);

        $parent = User::factory()->create()->assignRole(Role::Parent->value);
        Tuteur::factory()->create(['user_id' => $parent->id])->eleves()->attach($this->eleves[1]->id, ['lien' => 'mere']);
        $this->actingAs($parent, 'sanctum');

        $this->getJson("/api/eleves/{$this->eleves[1]->id}/absences")
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('totaux.heures_justifiees', 2)
            ->assertJsonPath('totaux.heures_non_justifiees', 1)
            ->assertJsonPath('totaux.retards', 1)
            ->assertJsonPath('totaux.a_justifier', 2);
        $this->getJson("/api/eleves/{$this->eleves[0]->id}/absences")->assertForbidden();
    }
}
