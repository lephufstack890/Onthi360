<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 24/10 — một ĐỀ sinh từ ma trận của kỳ thi (xem migration create_competition_exam_papers_table và
 * App\Services\Contest\ContestPaperService). Đã chốt thì assessment_id trỏ tới đề thật học sinh làm.
 */
class CompetitionExamPaper extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'competition_exam_id', 'label', 'assessment_id', 'matrix', 'exclude_used', 'draft', 'duration_minutes',
        'status', 'version', 'published_at', 'published_by', 'used_question_ids',
    ];

    protected $casts = [
        'matrix' => 'array',
        'draft' => 'array',
        'used_question_ids' => 'array',
        'exclude_used' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Đã chạy migration 001280 chưa — chưa thì mọi chỗ dùng đề ma trận coi như không có. */
    public static function supported(): bool
    {
        static $has = null;

        return $has ??= Schema::hasTable('competition_exam_papers');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CompetitionExam::class, 'competition_exam_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->assessment_id !== null;
    }
}
