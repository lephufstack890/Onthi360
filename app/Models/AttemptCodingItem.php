<?php

namespace App\Models;

use App\Enums\VerdictStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Bài nộp code của học sinh cho 1 bài lập trình con trong đề PDF (App\Models\
 * AssessmentCodingItem) — song song với attempt_answers (câu Coding kiểu structured), vì
 * AssessmentCodingItem không phải Question.
 *
 * SỬA 19/9 (7) — ghi chú cũ ở đây ("verdict luôn queued, chưa có sandbox chấm code thật") ĐÃ
 * HẾT ĐÚNG từ khi nối Judge0: PdfAttemptService::gradePendingCodingItems() chấm thật lúc nộp
 * bài và ghi verdict + score + số test đã qua (passed_tests/total_tests).
 */
class AttemptCodingItem extends Model
{
    protected $fillable = [
        'attempt_id', 'coding_item_id', 'code_source', 'language', 'verdict', 'score',
        'graded_at', 'submission_count',
        // SỬA 19/9 (7) — số test đã qua / tổng số test, để màn kết quả hiện được tỉ lệ AC.
        'passed_tests', 'total_tests',
    ];

    /**
     * SỬA 19/9 (7) — máy chủ đã chạy migration thêm 2 cột passed_tests/total_tests chưa?
     *
     * Cùng khuôn với CompetitionRegistration::supported(): mã mới được deploy TRƯỚC khi ai đó
     * kịp chạy "php artisan migrate" là chuyện xảy ra thật. Không có cái chốt này thì lần nộp
     * bài đầu tiên sau khi deploy sẽ đổ lỗi SQL "Unknown column" ngay giữa lúc học sinh đang
     * thi. Hỏi Schema đúng MỘT lần cho mỗi tiến trình rồi nhớ lại.
     */
    public static function supportsTestCounts(): bool
    {
        static $supported = null;

        if ($supported === null) {
            $supported = Schema::hasColumn('attempt_coding_items', 'passed_tests');
        }

        return $supported;
    }

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
