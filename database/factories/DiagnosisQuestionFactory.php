<?php

namespace Database\Factories;

use App\Models\DiagnosisQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagnosisQuestion>
 */
class DiagnosisQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagnosis_session_id' => DiagnosisSession::factory(),
            'question' => fake()->sentence().'?',
            'answer' => null,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'answer' => fake()->sentence(),
        ]);
    }
}
