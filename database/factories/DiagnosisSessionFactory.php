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
            'diagnosis' => self::diagnosisData(),
            'step' => 2,
        ]);
    }

    public function completed(): static
    {
        return $this->diagnosed()->state(fn (): array => [
            'final_result' => self::finalResultData(),
            'step' => 6,
            'status' => 'completed',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function diagnosisData(): array
    {
        return [
            'clarity_score' => 4,
            'dimension_scores' => [
                'audience' => ['score' => 0, 'reason' => 'No audience specified'],
                'problem' => ['score' => 1, 'reason' => 'Vague problem statement'],
                'offer' => ['score' => 1, 'reason' => 'Multiple services listed'],
                'value' => ['score' => 1, 'reason' => 'Generic value proposition'],
                'language' => ['score' => 1, 'reason' => 'Some jargon present'],
            ],
            'weaknesses' => [
                ['category' => 'AUDIENCE_MISSING', 'issue' => 'No target defined', 'explanation' => 'The description does not specify who this service is for.'],
            ],
            'strengths' => [
                ['area' => 'experience', 'feedback' => 'Shows practical knowledge.'],
            ],
            'coach_message' => 'You\'ve got a solid foundation. Let\'s sharpen it.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function finalResultData(): array
    {
        return [
            'new_clarity_score' => 8,
            'new_dimension_scores' => [
                'audience' => ['score' => 2, 'reason' => 'Specific audience defined'],
                'problem' => ['score' => 2, 'reason' => 'Clear problem statement'],
                'offer' => ['score' => 1, 'reason' => 'Focused but could be tighter'],
                'value' => ['score' => 2, 'reason' => 'Clear differentiation'],
                'language' => ['score' => 1, 'reason' => 'Mostly clear'],
            ],
            'service_description' => 'I help early-stage SaaS founders who just lost a client because their pitch was unclear. I rewrite your service description so potential clients immediately understand what you do and why they should choose you.',
            'value_proposition' => 'After working with me, you can explain your service in one sentence that makes prospects say yes.',
            'target_audience' => 'Freelancers and consultants who struggle to articulate what they do when a potential client asks.',
            'boundaries' => ['I don\'t build websites', 'I don\'t do ongoing marketing'],
            'one_liner' => 'I help freelancers explain what they do so clients actually hire them.',
            'next_steps' => [
                'Update your website homepage with the new service description.',
                'Test your new one-liner on 3 potential clients this week.',
                'Remove any services from your portfolio that don\'t match your new focus.',
            ],
            'coach_message' => 'Great improvement! Your description went from confusing to compelling.',
            'variants' => null,
        ];
    }
}
