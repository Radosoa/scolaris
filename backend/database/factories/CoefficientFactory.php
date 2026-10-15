<?php

namespace Database\Factories;

use App\Models\Coefficient;
use App\Models\Matiere;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coefficient>
 */
class CoefficientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'niveau_id' => Niveau::factory(),
            'matiere_id' => Matiere::factory(),
            'valeur' => $this->faker->randomElement([1, 2, 3, 4]),
        ];
    }
}
