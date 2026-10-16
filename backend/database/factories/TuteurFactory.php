<?php

namespace Database\Factories;

use App\Models\Tuteur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tuteur>
 */
class TuteurFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'telephone' => $this->faker->numerify('06 ## ## ## ##'),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
