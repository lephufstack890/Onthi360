<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JudgeSubmission extends Model
{
    protected $fillable = [
        'attempt_answer_id', 'external_submission_id', 'status', 'verdict',
        'raw_result', 'dispatched_at', 'completed_at',
    ];

    protected $casts = [
        'raw_result' => 'array',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function attemptAnswer(): BelongsTo
    {
        return $this->belongsTo(AttemptAnswer::class);
    }
}
