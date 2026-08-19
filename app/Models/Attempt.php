<?php

namespace App\Models;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    protected $fillable = [
        'user_id', 'assessment_id', 'assignment_id', 'class_room_id',
        // SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi") — ghi lại
        // lượt làm bài này thuộc đúng cuộc thi/kỳ thi nào TẠI THỜI ĐIỂM tạo Attempt, xem
        // migration ..._add_competition_columns_to_attempts_table.php + AttemptService::
        // startOrResume().
        'competition_id', 'competition_exam_id',
        'source', 'started_at', 'submitted_at', 'status', 'total_score', 'is_provisional',
    ];

    protected $casts = [
        'source' => AttemptSource::class,
        'status' => AttemptStatus::class,
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'is_provisional' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function competitionExam(): BelongsTo
    {
        return $this->belongsTo(CompetitionExam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /**
     * SỬA 19/8 (Giai đoạn 2 — đề PDF, 16/8 mục 1.2/6): 2 quan hệ song song với answers() ở
     * trên, chỉ có dữ liệu khi assessment content_mode=pdf_answer_sheet (rỗng, không lỗi,
     * khi content_mode=structured). Attempt dùng CHUNG cho cả 2 chế độ — không cần bảng/cột
     * attempts riêng.
     */
    public function answerKeys(): HasMany
    {
        return $this->hasMany(AttemptAnswerKey::class);
    }

    public function codingItems(): HasMany
    {
        return $this->hasMany(AttemptCodingItem::class);
    }

    /** Kết quả tổng là "tạm tính" tới khi mọi câu cần chấm hoàn tất (6.3). */
    public function recalculateProvisionalFlag(): void
    {
        $this->is_provisional = $this->answers()
            ->whereIn('verdict', ['pending', 'queued', 'judging'])
            ->exists();
    }
}
