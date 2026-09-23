<?php

namespace App\Jobs;

use App\Models\AttemptAnswer;
use App\Services\AttemptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SỬA 23/9 (khách: "bấm nộp đề trong luyện tập nó đứng luôn") — chấm 1 câu Lập trình của 1
 * lượt làm bài, chạy NỀN.
 *
 * Trước đây nộp bài là chấm ngay trong lúc trình duyệt chờ: đề 5 bài × 20 test = 100 lượt chạy
 * nối nhau, vượt thời gian chờ của web nên trang đứng im. Giờ mỗi câu là 1 việc riêng, điểm
 * hiện dần trên trang kết quả.
 *
 * · 1 câu = 1 job -> câu nào xong hiện điểm câu đó, một câu hỏng không kéo chết cả bài.
 * · Thử lại 2 lần, giãn cách 30 rồi 60 giây (máy chấm hay nghẽn nhất thời).
 * · Hỏng hẳn (hết 3 lượt thử) thì ghi verdict "lỗi hệ thống chấm bài" để trang kết quả thôi
 *   quay vòng "Đang chấm" mãi — KHÔNG ghi 0 điểm, chấm lại được bằng
 *   php artisan attempt:regrade-stuck.
 *
 * Máy chủ chưa bật tiến trình chạy nền: đặt QUEUE_CONNECTION=sync trong .env, Laravel chạy
 * thẳng tại chỗ y như hành vi cũ.
 */
class GradeCodingAnswerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Máy chấm chạy 20 test có thể lâu. Con số này phải nằm GIỮA hai mốc:
     *   · LỚN HƠN mức chờ tối đa của Judge0Client::runBatch() (tối đa 300s) — để khi máy chấm
     *     chậm quá thì chính runBatch ném ra lỗi NÓI RÕ LÝ DO, thay vì việc bị giết ngang.
     *   · NHỎ HƠN retry_after của hàng đợi (900s, xem config/queue.php) — để việc đang chạy
     *     không bị tiến trình khác nhận lại giữa chừng.
     */
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

        $attempts->gradeCodingAnswer($answer);
        $attempts->refreshScoreAfterGrading($answer->attempt);
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
