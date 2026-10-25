<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 25/10 — một đề ngân hàng thuộc kỳ thi/khảo sát (kỳ chọn nhiều đề, học sinh được bốc ngẫu nhiên 1 đề).
 * Xem migration 001300 và CompetitionExam::bankAssessmentIds() / ContestPaperService::bankAssessmentFor().
 */
class CompetitionExamAssessment extends Model
{
    protected $fillable = ['competition_exam_id', 'assessment_id', 'position'];

    /** Đã chạy migration 001300 chưa — chưa thì mỗi kỳ chỉ có 1 đề (assessment_id) như cũ. */
    public static function supported(): bool
    {
        static $has = null;

        return $has ??= Schema::hasTable('competition_exam_assessments');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CompetitionExam::class, 'competition_exam_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
