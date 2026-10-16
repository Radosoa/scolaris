<?php

namespace Database\Factories;

use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affectation_id' => Affectation::factory(),
            'periode_id' => Periode::factory(),
            'type' => 'devoir',
            'titre' => 'Devoir '.$this->faker->numberBetween(1, 9),
            'date' => fn (array $attributs) => Periode::find($attributs['periode_id'])->date_debut->copy()->addWeeks(2)->toDateString(),
            'bareme' => 20,
        ];
    }
}
