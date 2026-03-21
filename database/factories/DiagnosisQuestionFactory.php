<?php

namespace Database\Factories;

use App\Models\DiagnosisQuestion;
use App\Models\DiagnosisSession;
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
            'step' => 'describe',
            'question_key' => 'd1',
            'type' => 'single',
            'question' => fake()->sentence().'?',
            'intro_text' => null,
            'options' => [
                ['id' => 'a', 'label' => fake()->sentence()],
                ['id' => 'b', 'label' => fake()->sentence()],
                ['id' => 'c', 'label' => fake()->sentence()],
                ['id' => 'other', 'label' => 'My situation is different'],
            ],
            'answer' => null,
            'sort_order' => 0,
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'answer' => ['selected' => ['a'], 'other_text' => null],
        ]);
    }

    public function multiSelect(): static
    {
        return $this->state(fn (): array => [
            'type' => 'multi',
        ]);
    }
}
