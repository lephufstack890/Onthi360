<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Câu trả lời của học sinh cho 1 câu trong phiếu trả lời của đề PDF (App\Models\
 * AssessmentAnswerKey) — chấm ngay khi lưu (App\Services\PdfAttemptService::saveAnswerKey())
 * bằng AssessmentAnswerKey::isCorrect(), khác attempt_answers (dùng cho câu hỏi rời).
 */
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
