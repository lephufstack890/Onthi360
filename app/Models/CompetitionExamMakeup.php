<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 2/11 (khách: "kỳ kết thúc mà có học sinh quên làm thì người tổ chức / admin add vào làm rồi xếp hạng như bình
 * thường") — một học sinh được LÀM BÙ một kỳ đã kết thúc, tới hạn deadline_at (migration 001350).
 *
 * Kỳ đã kết thúc + có dòng này + chưa quá hạn → học sinh vào làm được như lúc kỳ đang mở (ContestEntryService::enter(),
 * AttemptService::competitionEntryDecision()). Hạn nộp của lượt làm bù = min(giờ bắt đầu + thời lượng đề, deadline_at) —
 * AttemptService::deadlineFor(). Bài ghi vào đúng kỳ nên vào bảng xếp hạng / bảng điểm / nhật ký như mọi bài khác.
 */
class CompetitionExamMakeup extends Model
{
    protected $fillable = ['competition_exam_id', 'student_id', 'added_by', 'deadline_at'];

    protected $casts = ['deadline_at' => 'datetime'];

    /** @var array<string, ?self> bộ nhớ đệm trong 1 lượt gọi (deadlineFor() chạy nhiều lần mỗi lần lưu bài) */
    private static array $memo = [];

    public static function supported(): bool
    {
        static $has = null;

        return $has ??= Schema::hasTable('competition_exam_makeups');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CompetitionExam::class, 'competition_exam_id');
    }

    /** Dòng làm bù của học sinh ở kỳ (còn hạn hay không), null = không được làm bù. */
    public static function rowFor(int $studentId, int $examId): ?self
    {
        if (! self::supported()) {
            return null;
        }
        $key = $examId.':'.$studentId;
        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = self::query()->where('competition_exam_id', $examId)->where('student_id', $studentId)->first();
        }

        return self::$memo[$key];
    }

    /** Kỳ đã kết thúc và học sinh đang có quyền làm bù còn hạn → dòng làm bù; ngược lại null. */
    public static function openFor(int $studentId, CompetitionExam $exam): ?self
    {
        if ($exam->computedStatus() !== 'ended') {
            return null;
        }
        $row = self::rowFor($studentId, (int) $exam->id);

        return ($row !== null && $row->deadline_at !== null && now()->lt($row->deadline_at)) ? $row : null;
    }

    /** Xoá bộ nhớ đệm (sau khi thêm / gỡ trong cùng lượt gọi). */
    public static function forgetMemo(): void
    {
        self::$memo = [];
    }
}
