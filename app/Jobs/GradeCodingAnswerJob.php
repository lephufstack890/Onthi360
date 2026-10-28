<?php

namespace App\Jobs;

use App\Models\AttemptAnswer;
use App\Services\AttemptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GradeCodingAnswerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $attemptAnswerId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 60];
    }

    public function handle(AttemptService $attempts): void
    {
        $answer = AttemptAnswer::with(['question', 'attempt.assessment.items'])->find($this->attemptAnswerId);

        if ($answer === null || $answer->attempt === null) {
            return;
        }

        // SỬA 28/10 — cùng khoá với lượt chấm ngay (Chấm câu / hộp kết quả hỏi điểm): câu đang được chỗ khác chấm thì thôi,
        // khỏi chạy máy chấm hai lần. Không có khoá (cache chưa hỗ trợ) thì chấm như cũ.
        $lock = null;
        try {
            $lock = \Illuminate\Support\Facades\Cache::lock('oi-grade-answer-'.$answer->id, 600);
            if (! $lock->get()) {
                return;
            }
        } catch (Throwable $e) {
            $lock = null;
        }

        try {
            $attempts->gradeCodingAnswer($answer);
            $attempts->refreshScoreAfterGrading($answer->attempt);
        } finally {
            $lock?->release();
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('Chấm nền thất bại cho câu trả lời #'.$this->attemptAnswerId, ['exception' => $e]);

        $answer = AttemptAnswer::with('attempt')->find($this->attemptAnswerId);

        if ($answer === null) {
            return;
        }

        app(AttemptService::class)->markCodingAnswerSystemError($answer);
    }
}
