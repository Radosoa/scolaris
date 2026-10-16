<?php

namespace Database\Factories;

use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_id' => Evaluation::factory(),
            'eleve_id' => Eleve::factory(),
            'valeur' => $this->faker->randomFloat(2, 0, 20),
            'absent' => false,
        ];
    }
}
