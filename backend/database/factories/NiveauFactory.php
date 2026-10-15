<?php

namespace Database\Factories;

use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Niveau>
 */
class NiveauFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ordre = $this->faker->unique()->numberBetween(1, 99);

        return [
            'libelle' => "Niveau {$ordre}",
            'ordre' => $ordre,
        ];
    }
}
