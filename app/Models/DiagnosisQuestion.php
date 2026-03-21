<?php

namespace App\Models;

use Database\Factories\DiagnosisQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisQuestion extends Model
{
    /** @use HasFactory<DiagnosisQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'diagnosis_session_id',
        'step',
        'question_key',
        'type',
        'question',
        'intro_text',
        'options',
        'answer',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'answer' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DiagnosisSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DiagnosisSession::class, 'diagnosis_session_id');
    }
}
