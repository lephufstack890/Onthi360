<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 7/10 — bài mẫu do ADMIN chỉ định cho một bài tập (từ trang Nhật ký nộp bài).
 * Xem migration create_practice_sample_submissions_table.
 */
class PracticeSampleSubmission extends Model
{
    protected $fillable = ['question_id', 'attempt_answer_id', 'submitter_name', 'language', 'code', 'designated_by'];

    protected $casts = [
        'question_id' => 'integer',
        'attempt_answer_id' => 'integer',
    ];

    /** Bảng đã được tạo trên máy chủ chưa (deploy mã trước khi chạy migrate vẫn không vỡ trang). */
    public static function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('practice_sample_submissions');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designated_by');
    }
}
