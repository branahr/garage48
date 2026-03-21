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
     * @return HasMany<DiagnosisQuestion, $this>
     */
    public function questionsForStep(string $step): HasMany
    {
        return $this->questions()->where('step', $step);
    }

    public function allQuestionsAnswered(): bool
    {
        return $this->questions->isNotEmpty()
            && $this->questions->every(fn (DiagnosisQuestion $q): bool => $q->answer !== null);
    }
}
