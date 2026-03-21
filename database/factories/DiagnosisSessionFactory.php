<?php

namespace Database\Factories;

use App\Models\DiagnosisSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagnosisSession>
 */
class DiagnosisSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_description' => fake()->paragraph(3),
            'diagnosis' => null,
            'final_result' => null,
            'step' => 1,
            'status' => 'in_progress',
        ];
    }

    public function diagnosed(): static
    {
        return $this->state(fn (): array => [
            'diagnosis' => [
                'score' => fake()->numberBetween(1, 10),
                'summary' => fake()->sentence(),
                'strengths' => [fake()->sentence()],
                'weaknesses' => [fake()->sentence()],
                'missing_target_audience' => fake()->sentence(),
                'missing_value_proposition' => fake()->sentence(),
                'jargon' => [fake()->word()],
                'decision_questions_needed' => fake()->numberBetween(4, 8),
                'decision_questions_reason' => fake()->sentence(),
            ],
            'step' => 2,
        ]);
    }

    public function completed(): static
    {
        return $this->diagnosed()->state(fn (): array => [
            'final_result' => [
                'rewritten_description' => fake()->paragraph(),
                'target_audience' => fake()->sentence(),
                'value_proposition' => fake()->sentence(),
                'positioning_statement' => fake()->sentence(),
                'next_steps' => [fake()->sentence(), fake()->sentence()],
            ],
            'step' => 4,
            'status' => 'completed',
        ]);
    }
}
