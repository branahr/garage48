<?php

namespace App\Models;

use Database\Factories\DiagnosisSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosisSession extends Model
{
    /** @use HasFactory<DiagnosisSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'service_description',
        'diagnosis',
        'final_result',
        'step',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diagnosis' => 'array',
            'final_result' => 'array',
        ];
    }

    /**
     * @return HasMany<DiagnosisQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(DiagnosisQuestion::class)->orderBy('sort_order');
    }

    /**
     * Get questions for a specific step (describe, decide, value).
     *
     * @return HasMany<DiagnosisQuestion, $this>
     */
    public function questionsForStep(string $step): HasMany
    {
        return $this->questions()->where('step', $step);
    }

    /**
     * Compute routing from dimension scores (authoritative).
     *
     * @return array{describe: string, decide: string, value: string}
     */
    public function computedRouting(): array
    {
        $scores = $this->diagnosis['dimension_scores'] ?? [];

        $audience = $scores['audience']['score'] ?? 0;
        $problem = $scores['problem']['score'] ?? 0;
        $offer = $scores['offer']['score'] ?? 0;
        $value = $scores['value']['score'] ?? 0;

        return [
            'describe' => match ($audience) {
                0 => 'deep',
                1 => 'light',
                default => 'skip',
            },
            'decide' => match (true) {
                $problem === 0 || $offer === 0 => 'deep',
                $problem === 1 || $offer === 1 => 'light',
                default => 'skip',
            },
            'value' => match ($value) {
                0 => 'deep',
                1 => 'light',
                default => 'skip',
            },
        ];
    }

    public function allQuestionsAnswered(): bool
    {
        return $this->questions->isNotEmpty()
            && $this->questions->every(fn (DiagnosisQuestion $q): bool => $q->answer !== null);
    }
}
