<?php

namespace App\Console\Commands;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * SỬA 23/9 (khách: "chấm hơn 15 phút chưa xong") — GÕ 1 LỆNH, XEM ĐƯỢC TOÀN CẢNH hàng đợi
 * chấm bài, thay vì phải chạy 5-6 lệnh mysql/tail rời rạc rồi tự ghép.
 *
 *   php artisan queue:diagnose
 *
 * In ra: cấu hình hàng đợi đang dùng, số việc đang chờ / đang bị giữ / đã hỏng, việc cũ nhất
 * chờ bao lâu, và danh sách câu Lập trình còn kẹt "Đang chấm" kèm số phút đã kẹt.
 */
class QueueDiagnose extends Command
{
    protected $signature = 'queue:diagnose';

    protected $description = 'Xem tình trạng hàng đợi chấm bài và các câu đang kẹt.';

    public function handle(): int
    {
        $connection = (string) config('queue.default');
        $retryAfter = config('queue.connections.'.$connection.'.retry_after');

        $this->line('── Cấu hình ──');
        $this->line('  Hàng đợi            : '.$connection);
        $this->line('  retry_after         : '.($retryAfter ?? '—').' giây');
        $this->line('  Job timeout         : '.(new \App\Jobs\GradeCodingAnswerJob(0))->timeout.' giây');

        if (is_int($retryAfter) && $retryAfter <= 300) {
            $this->warn('  ⚠ retry_after NHỎ HƠN/BẰNG thời gian chạy của việc chấm — việc đang chạy sẽ bị tiến trình khác cướp giữa chừng.');
        }

        $this->line('  Máy chấm Judge0     : '.config('judge0.base_url'));
        $this->line('  '.$this->judge0Health());
        $this->newLine();

        if ($connection === 'database') {
            $now = time();
            $pending = DB::table('jobs')->whereNull('reserved_at')->count();
            $reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();
            $oldest = DB::table('jobs')->orderBy('created_at')->first(['created_at', 'attempts']);
            $failed = DB::table('failed_jobs')->count();

            $this->line('── Hàng đợi ──');
            $this->line('  Đang chờ            : '.$pending);
            $this->line('  Đang có máy giữ     : '.$reserved);
            $this->line('  Đã hỏng (failed)    : '.$failed);
            $this->line('  Việc cũ nhất        : '.($oldest === null
                ? 'không có'
                : round(($now - (int) $oldest->created_at) / 60, 1).' phút trước, đã thử '.$oldest->attempts.' lượt'));

            if ($pending > 0 && $reserved === 0) {
                $this->warn('  ⚠ Có việc chờ nhưng KHÔNG máy nào đang chạy — tiến trình chấm nền không hoạt động (kiểm tra crontab).');
            }

            $this->newLine();
        }

        $stuck = AttemptAnswer::query()
            ->with('attempt')
            ->whereIn('verdict', ['pending', 'queued', 'judging', 'system_error'])
            ->whereNotNull('code_source')
            ->whereHas('question', fn ($q) => $q->where('type', QuestionType::Coding->value))
            ->whereHas('attempt', fn ($q) => $q->whereNotNull('submitted_at'))
            ->get();

        $this->line('── Câu Lập trình chưa có kết quả ──');

        if ($stuck->isEmpty()) {
            $this->info('  Không có câu nào đang kẹt.');

            return self::SUCCESS;
        }

        foreach ($stuck as $answer) {
            $minutes = $answer->attempt?->submitted_at?->diffInMinutes(now());

            $this->line(sprintf(
                '  Lượt #%d · câu trả lời #%d · %s · kẹt %s phút',
                $answer->attempt_id,
                $answer->id,
                $answer->verdict->value,
                $minutes === null ? '—' : round($minutes)
            ));
        }

        $this->newLine();
        $this->line('  Chấm lại ngay: php artisan attempt:regrade-stuck --minutes=0 --now');

        return self::SUCCESS;
    }

    /**
     * Gọi thử máy chấm 1 phát để biết nó CÒN SỐNG hay không, và trả lời nhanh cỡ nào — câu
     * kẹt "Đang chấm" mà Judge0 không trả lời thì khỏi phải đoán thêm.
     */
    private function judge0Health(): string
    {
        $baseUrl = rtrim((string) config('judge0.base_url'), '/');
        $headers = [(string) config('judge0.auth_header') => (string) config('judge0.auth_token')];

        $startedAt = microtime(true);

        try {
            $response = Http::baseUrl($baseUrl)->withHeaders($headers)
                ->connectTimeout(5)->timeout(10)->get('/about');
        } catch (\Throwable $e) {
            return 'Trạng thái máy chấm  : KHÔNG GỌI ĐƯỢC — '.$e->getMessage();
        }

        $ms = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            return 'Trạng thái máy chấm  : lỗi HTTP '.$response->status().' ('.$ms.' ms)';
        }

        return 'Trạng thái máy chấm  : OK — trả lời sau '.$ms.' ms';
    }
}
