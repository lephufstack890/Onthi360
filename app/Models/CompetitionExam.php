<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kỳ thi (đề) bên trong 1 Competition — 1 Competition có thể gồm NHIỀU kỳ thi (vd: Vòng 1,
 * Vòng 2...), mỗi kỳ thi tham chiếu 1 Assessment riêng. Khác với Competition::assessment_id
 * (đề tham chiếu DUY NHẤT cũ, giữ để tương thích ngược — xem migration backfill
 * ..._create_competition_exams_table.php) — kỳ thi cho phép nhiều đề trong cùng 1 cuộc thi,
 * mỗi kỳ thi có bảng xếp hạng riêng (LeaderboardEntry scope=competition_exam) ngoài bảng
 * tổng (scope=competition, xem CompetitionService::recomputeAggregateFromExams()).
 */
class CompetitionExam extends Model
{
    protected $fillable = ['competition_id', 'assessment_id', 'title', 'order', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /**
     * now() có nằm trong [starts_at, ends_at] không — cột nào null thì bỏ qua điều kiện đó
     * (giống App\Services\Public\CompetitionService's private isWithinWindow(), áp cho từng
     * kỳ thi thay vì cả cuộc thi).
     */
    public function isOngoing(): bool
    {
        if ($this->starts_at !== null && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && now()->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /** title rỗng (đa số kỳ thi backfill từ cuộc thi cũ) thì hiển thị tên đề tham chiếu. */
    public function displayTitle(): string
    {
        return $this->title ?: ($this->assessment->title ?? 'Kỳ thi #'.$this->id);
    }
}
