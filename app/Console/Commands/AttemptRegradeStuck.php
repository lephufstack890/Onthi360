<?php

namespace App\Console\Commands;

use App\Enums\QuestionType;
use App\Jobs\GradeCodingAnswerJob;
use App\Models\AttemptAnswer;
use App\Services\AttemptService;
use Illuminate\Console\Command;
use Throwable;

/**
 * SỬA 23/9 (khách: "chấm hơn 15p rồi chưa xong") — gom các câu Lập trình còn kẹt "Đang chấm"
 * (hoặc đã báo lỗi hệ thống chấm bài) của những lượt ĐÃ NỘP và chấm lại.
 *
 *   php artisan attempt:regrade-stuck --minutes=10            (đẩy lại vào hàng đợi)
 *   php artisan attempt:regrade-stuck --minutes=10 --now      (chấm NGAY tại chỗ, không qua hàng đợi)
 *   php artisan attempt:regrade-stuck --attempt=123 --now     (chỉ 1 lượt làm bài)
 *
 * --now dùng khi nghi tiến trình chạy nền không chạy: chấm thẳng trong lệnh này, thấy ngay
 * lỗi thật nếu máy chấm Judge0 không tới được. Không đụng câu đã chấm xong.
 */
class AttemptRegradeStuck extends Command
{
    protected $signature = 'attempt:regrade-stuck
        {--minutes=5 : Chỉ lấy câu kẹt lâu hơn bấy nhiêu phút}
        {--attempt= : Chỉ 1 lượt làm bài}
        {--now : Chấm ngay tại chỗ thay vì đẩy vào hàng đợi}';

    protected $description = 'Chấm lại các câu Lập trình còn kẹt "Đang chấm".';

    public function handle(AttemptService $attempts): int
    {
        $minutes = (int) $this->option('minutes');
        $now = (bool) $this->option('now');

        $query = AttemptAnswer::query()
            ->with(['question', 'attempt.assessment.items'])
            ->whereIn('verdict', ['pending', 'queued', 'judging', 'system_error'])
            ->whereNotNull('code_source')
            ->whereHas('question', fn ($q) => $q->where('type', QuestionType::Coding->value))
            ->whereHas('attempt', fn ($q) => $q->whereNotNull('submitted_at'));

        if (filled($this->option('attempt'))) {
            $query->where('attempt_id', (int) $this->option('attempt'));
        } elseif ($minutes > 0) {
            $query->whereHas('attempt', fn ($q) => $q->where('submitted_at', '<=', now()->subMinutes($minutes)));
        }

        $answers = $query->get();

        if ($answers->isEmpty()) {
            $this->info('Không có câu nào đang kẹt.');

            return self::SUCCESS;
        }

        $this->line($answers->count().' câu đang kẹt.');

        foreach ($answers as $answer) {
            // system_error là trạng thái cuối — phải mở lại thì gradeCodingAnswer() mới chấm.
            if ($answer->verdict->value === 'system_error') {
                $answer->forceFill(['verdict' => 'judging', 'graded_at' => null])->save();
                $answer->refresh();
            }

            if (! $now) {
                GradeCodingAnswerJob::dispatch($answer->id);
                $this->line('  · Câu trả lời #'.$answer->id.' — đã đẩy lại vào hàng đợi.');

                continue;
            }

            try {
                $attempts->gradeCodingAnswer($answer);
                $attempts->refreshScoreAfterGrading($answer->attempt);
                $answer->refresh();
                $this->line('  · Câu trả lời #'.$answer->id.' → '.$answer->verdict->label().' ('.($answer->score ?? '—').' điểm)');
            } catch (Throwable $e) {
                $this->error('  · Câu trả lời #'.$answer->id.' hỏng: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
