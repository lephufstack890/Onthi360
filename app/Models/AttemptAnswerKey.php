<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswerKey extends Model
{
    protected $fillable = ['attempt_id', 'answer_key_id', 'submitted_answer', 'is_correct', 'score', 'graded_at'];

    protected $casts = [
        'submitted_answer' => 'array',
        'is_correct' => 'boolean',
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function answerKey(): BelongsTo
    {
        return $this->belongsTo(AssessmentAnswerKey::class, 'answer_key_id');
    }
}
