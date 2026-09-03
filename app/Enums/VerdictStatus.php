<?php

namespace App\Enums;

enum VerdictStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Judging = 'judging';
    case Accepted = 'accepted';
    case WrongAnswer = 'wrong_answer';
    case TimeLimitExceeded = 'time_limit_exceeded';
    case MemoryLimitExceeded = 'memory_limit_exceeded';
    case RuntimeError = 'runtime_error';
    case CompileError = 'compile_error';
    case SystemError = 'system_error';

    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::Queued, self::Judging], true);
    }

    /**
     * SỬA 3/9 (nối máy chấm Judge0 thật) — nhãn tiếng Việt hiện cho học sinh/giáo viên biết
     * RÕ verdict là gì (thay vì chỉ "✕ Chưa đúng" chung chung, không phân biệt được sai đáp án
     * với lỗi biên dịch/quá thời gian) — xem Student\PracticeByQuestionService::
     * judgeCodingAnswer() (lưu vào feedback['codingVerdict']) + by-question-play.blade.php/
     * exercise-play.blade.php (nơi hiện nhãn này).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending, self::Queued, self::Judging => 'Đang chấm',
            self::Accepted => 'Chính xác',
            self::WrongAnswer => 'Sai kết quả (Wrong Answer)',
            self::TimeLimitExceeded => 'Quá thời gian (Time Limit Exceeded)',
            self::MemoryLimitExceeded => 'Vượt bộ nhớ (Memory Limit Exceeded)',
            self::RuntimeError => 'Lỗi runtime lúc chạy (Runtime Error)',
            self::CompileError => 'Lỗi biên dịch (Compilation Error)',
            self::SystemError => 'Lỗi hệ thống chấm bài (không phải lỗi của bạn)',
        };
    }
}
