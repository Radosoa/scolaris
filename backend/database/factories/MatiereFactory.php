<?php

namespace Database\Factories;

use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matiere>
 */
class MatiereFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => ucfirst($this->faker->unique()->word()),
            'code' => strtoupper($this->faker->unique()->bothify('???#')),
        ];
    }
}
