<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\CodeJudgingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * SỬA 21/9 (khách: "20 test đều sai hết") — soi MỘT câu Lập trình từ đầu tới cuối bằng 1 lệnh:
 * in từng test đang lưu trong DB (dạng JSON để thấy rõ chuỗi rỗng / xuống dòng / khoảng trắng),
 * rồi chấm thật qua Judge0 và in trạng thái + output thật của từng test.
 *
 *   php artisan judge0:debug-question 27
 *   php artisan judge0:debug-question 27 --file=bai.cpp
 *
 * Không có --file thì dùng sẵn chương trình a+b (long long). KHÔNG in token/secret nào.
 */
class Judge0DebugQuestion extends Command
{
    protected $signature = 'judge0:debug-question {question : ID hoặc mã câu hỏi} {--file= : File mã nguồn muốn chấm thử} {--lang=cpp : cpp hoặc python}';

    protected $description = 'In test case đang lưu của 1 câu Lập trình và chấm thử qua Judge0 để tìm lý do sai.';

    public function handle(CodeJudgingService $judging): int
    {
        $key = (string) $this->argument('question');
        $question = Question::query()->whereKey(ctype_digit($key) ? (int) $key : 0)->orWhere('code', $key)->first();

        if ($question === null) {
            $this->error("Không tìm thấy câu hỏi '{$key}'.");

            return self::FAILURE;
        }

        $this->line("Câu #{$question->id} · {$question->code} · {$question->title}");
        $this->line('Loại: '.($question->type->value ?? $question->type).' · Phiên bản: v'.$question->version.' · Trạng thái: '.($question->status->value ?? $question->status));

        $newer = Question::query()->where('parent_version_id', $question->id)->get(['id', 'version', 'status']);
        foreach ($newer as $n) {
            $this->warn("  ⚠ Có phiên bản mới hơn: câu #{$n->id} (v{$n->version}, ".($n->status->value ?? $n->status).') — sửa ở admin có thể đã lưu vào bản này, học sinh vẫn làm bản cũ.');
        }

        $config = $question->grading_config ?? [];
        $tests = collect($config['test_cases'] ?? [])->values();
        if (! empty($config['file_io'])) {
            $this->line('Đề đọc/ghi file: '.json_encode($config['file_io']).' (máy chấm tự nối file ↔ stdin/stdout).');
        }
        $this->line('Số test: '.$tests->count().' · time_limit_ms='.($config['time_limit_ms'] ?? '-').' · memory_limit_mb='.($config['memory_limit_mb'] ?? '-'));
        $this->newLine();

        if ($tests->isEmpty()) {
            $this->error('Câu này KHÔNG có test nào.');

            return self::FAILURE;
        }

        $j = fn ($v) => json_encode((string) $v, JSON_UNESCAPED_UNICODE);

        $source = "#include <iostream>\nusing namespace std;\nint main(){ long long a,b; cin>>a>>b; cout<<a+b; }\n";
        if ($file = $this->option('file')) {
            if (! is_file($file)) {
                $this->error("Không đọc được file {$file}");

                return self::FAILURE;
            }
            $source = (string) file_get_contents($file);
        }

        $cases = $tests->map(fn ($t) => ['input' => (string) ($t['input'] ?? ''), 'expected_output' => (string) ($t['output'] ?? '')])->all();

        try {
            $result = $judging->judge($source, (string) $this->option('lang'), $cases,
                (int) ($config['time_limit_ms'] ?? 5000), (int) ($config['memory_limit_mb'] ?? 256) * 1024, $config['file_io'] ?? null);
        } catch (Throwable $e) {
            $this->error('Không chấm được: '.$e->getMessage());
            foreach ($tests as $i => $t) {
                $this->line(sprintf('#%-2d vào=%s  ra=%s', $i + 1, $j($t['input'] ?? ''), $j($t['output'] ?? '')));
            }

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['details'] as $d) {
            $rows[] = [
                $d['index'],
                mb_strimwidth($j($d['input']), 0, 40, '…'),
                mb_strimwidth($j($d['expectedOutput']), 0, 28, '…'),
                mb_strimwidth($j($d['actualOutput'] ?? ''), 0, 28, '…'),
                $d['status'],
            ];
        }
        $this->table(['#', 'Dữ liệu vào', 'Kết quả mong đợi', 'Output thật', 'Judge0'], $rows);

        $passed = collect($result['details'])->where('isAccepted', true)->count();
        $this->line("=> Đúng {$passed}/".count($result['details']).' · Kết luận: '.$result['verdict']->label());

        $first = collect($result['details'])->first(fn ($d) => $d['compileOutput'] || $d['stderr']);
        if ($first) {
            $this->newLine();
            $this->warn('Thông báo lỗi (test #'.$first['index'].'):');
            $this->line(mb_substr((string) ($first['compileOutput'] ?: $first['stderr']), 0, 1500));
        }

        return self::SUCCESS;
    }
}
