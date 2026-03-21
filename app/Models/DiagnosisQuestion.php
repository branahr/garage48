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
        'question',
        'answer',
        'sort_order',
    ];

    /**
     * @return BelongsTo<DiagnosisSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DiagnosisSession::class, 'diagnosis_session_id');
    }
}
