<?php

namespace App\Models;

use App\Enums\VerdictStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttemptAnswer extends Model
{
    use Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'attempt_id', 'question_id', 'answer', 'code_source', 'language',
        'verdict', 'score', 'graded_at', 'submission_count',
        // SỬA 19/9 (8) — số test đã qua / tổng số test của câu Lập trình, để màn tổng kết
        // nói được "Đúng 4/20 test" thay vì chỉ một chữ "Sai".
        'passed_tests', 'total_tests',
    ];

    /**
     * SỬA 19/9 (8) — máy chủ đã chạy migration thêm 2 cột passed_tests/total_tests chưa?
     *
     * Cùng khuôn với AttemptCodingItem::supportsTestCounts(): deploy mã mới TRƯỚC khi kịp chạy
     * "php artisan migrate" là chuyện xảy ra thật, không có chốt này thì lần nộp bài đầu tiên
     * sau khi deploy sẽ đổ lỗi SQL "Unknown column" ngay giữa lúc học sinh đang làm bài.
     */
    public static function supportsTestCounts(): bool
    {
        static $supported = null;

        if ($supported === null) {
            $supported = Schema::hasColumn('attempt_answers', 'passed_tests');
        }

        return $supported;
    }

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
