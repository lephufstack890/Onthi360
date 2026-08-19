<?php

namespace App\Models;

use App\Enums\VerdictStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bài nộp code của học sinh cho 1 bài lập trình con trong đề PDF (App\Models\
 * AssessmentCodingItem) — song song với attempt_answers (câu Coding kiểu structured), vì
 * AssessmentCodingItem không phải Question. Verdict luôn "queued" — chưa có sandbox chấm
 * code thật (giống giới hạn hiện tại của luồng OJ câu hỏi rời).
 */
class AttemptCodingItem extends Model
{
    protected $fillable = [
        'attempt_id', 'coding_item_id', 'code_source', 'language', 'verdict', 'score',
        'graded_at', 'submission_count',
    ];

    protected $casts = [
        'verdict' => VerdictStatus::class,
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function codingItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentCodingItem::class, 'coding_item_id');
    }
}
