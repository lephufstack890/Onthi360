<?php

namespace App\Console\Commands;

use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Services\CodeJudgingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * SỬA 24/9 (khách: "chấm lâu quá, muốn 3-6s/bài") — ĐỐI CHIẾU hai cách chấm trên CÙNG một bài
 * làm, trước khi dám bật chấm gộp trên máy thật.
 *
 *   php artisan judge0:compare-bundled 12                  (lấy mã nguồn của lượt làm gần nhất)
 *   php artisan judge0:compare-bundled 12 --answer=44      (một câu trả lời cụ thể)
 *   php artisan judge0:compare-bundled 12 --file=/tmp/a.cpp --language=cpp
 *
 * Chấm 2 lần: một lần ép TẮT chấm gộp (cách cũ), một lần ép BẬT. Rồi so verdict tổng và kết
 * quả TỪNG test. Lệch một test cũng báo đỏ.
 *
 * Chấm nhanh mà sai thì tệ hơn chấm chậm — đừng bật JUDGE0_BUNDLED_RUN=true khi lệnh này chưa
 * báo khớp.
 */
class Judge0CompareBundled extends Command
{
    protected $signature = 'judge0:compare-bundled
        {question : ID câu hỏi Lập trình}
        {--answer= : ID attempt_answers lấy mã nguồn}
        {--file= : Đường dẫn tệp mã nguồn}
        {--language=cpp : cpp hoặc python (dùng với --file)}';

    protected $description = 'Chấm cùng một bài bằng cả 2 cách (cũ và gộp) rồi so từng test.';

    public function handle(CodeJudgingService $judging): int
    {
        $question = Question::find((int) $this->argument('question'));

        if ($question === null) {
            $this->error('Không tìm thấy câu hỏi.');

            return self::FAILURE;
        }

        $config = $question->grading_config ?? [];
        $testCases = collect($config['test_cases'] ?? [])
            ->map(fn ($tc) => ['input' => (string) ($tc['input'] ?? ''), 'expected_output' => (string) ($tc['output'] ?? '')])
            ->all();

        if ($testCases === []) {
            $this->error('Câu này chưa có test case nào.');

            return self::FAILURE;
        }

        [$source, $language, $origin] = $this->resolveSource($question);

        if ($source === null) {
            $this->error('Không lấy được mã nguồn để chấm thử. Dùng --answer= hoặc --file=.');

            return self::FAILURE;
        }

        $timeLimitMs = (int) ($config['time_limit_ms'] ?? 5000);
        $memoryLimitKb = (int) ($config['memory_limit_mb'] ?? 256) * 1024;
        $fileIo = $config['file_io'] ?? null;

        $this->line('Câu hỏi   : #'.$question->id.' — '.$question->title);
        $this->line('Mã nguồn  : '.$origin);
        $this->line('Ngôn ngữ  : '.$language);
        $this->line('Số test   : '.count($testCases));
        $this->newLine();

        try {
            $this->line('Chấm theo CÁCH CŨ (mỗi test một bài nộp)…');
            config(['judge0.bundled_run' => false]);
            $t0 = microtime(true);
            $classic = $judging->judge($source, $language, $testCases, $timeLimitMs, $memoryLimitKb, $fileIo);
            $classicSeconds = microtime(true) - $t0;

            $this->line('Chấm theo CÁCH GỘP (một bài nộp cho cả bài)…');
            config(['judge0.bundled_run' => true]);
            $t0 = microtime(true);
            $bundled = $judging->judge($source, $language, $testCases, $timeLimitMs, $memoryLimitKb, $fileIo);
            $bundledSeconds = microtime(true) - $t0;
        } catch (Throwable $e) {
            $this->error('Chấm thử hỏng: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('── Thời gian ──');
        $this->line(sprintf('  Cách cũ : %6.2f s', $classicSeconds));
        $this->line(sprintf('  Cách gộp: %6.2f s', $bundledSeconds));

        if ($bundledSeconds > 0 && $classicSeconds / $bundledSeconds >= 1.5) {
            $this->info(sprintf('  Nhanh hơn %.1f lần.', $classicSeconds / $bundledSeconds));
        } else {
            $this->warn('  ⚠ Hai cách gần bằng nhau — nhiều khả năng chấm gộp đã TỰ QUAY VỀ cách cũ.');
            $this->warn('    Xem storage/logs/laravel.log, tìm dòng "Chấm gộp" để biết lý do.');
        }

        $this->newLine();
        $this->line('── Đối chiếu kết quả ──');

        $problems = [];

        if ($classic['verdict'] !== $bundled['verdict']) {
            $problems[] = 'Verdict tổng lệch: cũ='.$classic['verdict']->value.' / gộp='.$bundled['verdict']->value;
        }

        $cd = $classic['details'];
        $bd = $bundled['details'];

        if (count($cd) !== count($bd)) {
            $problems[] = 'Số test lệch: cũ='.count($cd).' / gộp='.count($bd);
        } else {
            foreach ($cd as $i => $c) {
                $b = $bd[$i];

                if (($c['isAccepted'] ?? null) !== ($b['isAccepted'] ?? null)) {
                    $problems[] = sprintf(
                        'Test %d lệch: cũ=%s / gộp=%s',
                        $i + 1,
                        ($c['isAccepted'] ?? false) ? 'ĐÚNG' : 'SAI',
                        ($b['isAccepted'] ?? false) ? 'ĐÚNG' : 'SAI'
                    );
                }
            }
        }

        $passedClassic = collect($cd)->where('isAccepted', true)->count();
        $passedBundled = collect($bd)->where('isAccepted', true)->count();

        $this->line(sprintf('  Cách cũ : %d/%d test đúng · %s', $passedClassic, count($cd), $classic['verdict']->label()));
        $this->line(sprintf('  Cách gộp: %d/%d test đúng · %s', $passedBundled, count($bd), $bundled['verdict']->label()));
        $this->newLine();

        if ($problems === []) {
            $this->info('  ✓ KHỚP HOÀN TOÀN — bật được JUDGE0_BUNDLED_RUN=true.');

            return self::SUCCESS;
        }

        foreach ($problems as $p) {
            $this->error('  ✗ '.$p);
        }

        $this->newLine();
        $this->error('  ĐỪNG bật chấm gộp khi còn dòng đỏ ở trên.');

        return self::FAILURE;
    }

    /** @return array{0: ?string, 1: string, 2: string} [mã nguồn, ngôn ngữ, mô tả nguồn lấy] */
    private function resolveSource(Question $question): array
    {
        if (filled($this->option('file'))) {
            $path = (string) $this->option('file');

            if (! is_file($path)) {
                return [null, '', ''];
            }

            return [(string) file_get_contents($path), (string) $this->option('language'), 'tệp '.$path];
        }

        $query = AttemptAnswer::query()->whereNotNull('code_source');

        if (filled($this->option('answer'))) {
            $query->whereKey((int) $this->option('answer'));
        } else {
            $query->where('question_id', $question->id)->latest('id');
        }

        $answer = $query->first();

        if ($answer === null) {
            return [null, '', ''];
        }

        return [(string) $answer->code_source, (string) ($answer->language ?? 'cpp'), 'câu trả lời #'.$answer->id];
    }
}
