<?php

namespace App\Models;

use App\Enums\VerdictStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttemptAnswer extends Model
{
    use Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'attempt_id', 'question_id', 'answer', 'code_source', 'language',
        'verdict', 'score', 'graded_at', 'submission_count',
    ];

    protected $casts = [
        'answer' => 'array',
        'verdict' => VerdictStatus::class,
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function judgeSubmissions(): HasMany
    {
        return $this->hasMany(JudgeSubmission::class);
    }
}
