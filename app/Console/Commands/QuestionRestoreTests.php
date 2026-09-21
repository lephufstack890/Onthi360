<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;

/**
 * SỬA 21/9 (khách: "chấm sai hết mặc dù chạy test ok") — câu Lập trình bị form Sửa cũ xoá mất
 * dữ liệu vào của test (input rỗng). Nếu đã có bản test đúng ở câu khác (thường là PHIÊN BẢN
 * MỚI được tạo khi sửa câu đã có người làm — bản Nháp, học sinh không dùng tới), lệnh này chép
 * test đúng về lại câu đang dùng.
 *
 *   php artisan question:restore-tests 1              (tự tìm phiên bản mới hơn của câu #1)
 *   php artisan question:restore-tests 1 --from=45    (chép từ câu #45)
 *
 * Chỉ chép test_cases (+ time/memory limit, languages, file_io nếu nguồn có), hỏi xác nhận
 * trước khi ghi. Không đụng tới bài làm cũ.
 */
class QuestionRestoreTests extends Command
{
    protected $signature = 'question:restore-tests {target : ID câu đang bị hỏng test} {--from= : ID câu có test đúng}';

    protected $description = 'Chép test case đúng từ câu khác (mặc định: phiên bản mới hơn) về câu đang bị mất dữ liệu vào.';

    public function handle(): int
    {
        $target = Question::find((int) $this->argument('target'));
        if ($target === null) {
            $this->error('Không tìm thấy câu cần sửa.');

            return self::FAILURE;
        }

        $source = $this->option('from')
            ? Question::find((int) $this->option('from'))
            : Question::query()->where('parent_version_id', $target->id)->orderByDesc('version')->first();

        if ($source === null) {
            $this->error('Không tìm thấy câu nguồn có test đúng. Dùng --from=<ID> để chỉ định.');

            return self::FAILURE;
        }

        $summary = function (Question $q): string {
            $tests = collect($q->grading_config['test_cases'] ?? []);
            $emptyInputs = $tests->filter(fn ($t) => trim((string) ($t['input'] ?? '')) === '')->count();

            return "#{$q->id} v{$q->version} · {$tests->count()} test · {$emptyInputs} test có dữ liệu vào RỖNG";
        };

        $this->line('Câu đang dùng : '.$summary($target).' · '.$target->title);
        $this->line('Lấy test từ   : '.$summary($source).' · '.$source->title);

        $sourceConfig = $source->grading_config ?? [];
        if (empty($sourceConfig['test_cases'])) {
            $this->error('Câu nguồn không có test nào — dừng.');

            return self::FAILURE;
        }

        if (! $this->confirm('Ghi đè test của câu #'.$target->id.' bằng test của câu #'.$source->id.'?', true)) {
            return self::SUCCESS;
        }

        $config = $target->grading_config ?? [];
        foreach (['test_cases', 'time_limit_ms', 'memory_limit_mb', 'languages', 'file_io'] as $key) {
            if (array_key_exists($key, $sourceConfig)) {
                $config[$key] = $sourceConfig[$key];
            }
        }
        $target->grading_config = $config;
        $target->save();

        $this->info('Đã chép xong. Sau khi sửa: '.$summary($target->fresh()));

        return self::SUCCESS;
    }
}
