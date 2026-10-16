<?php

namespace Database\Factories;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affectation>
 */
class AffectationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enseignant_id' => Enseignant::factory(),
            'matiere_id' => Matiere::factory(),
            'classe_id' => Classe::factory(),
        ];
    }
}
