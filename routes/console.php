<?php

use App\Models\Question;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * SỬA 19/8 — vá DỮ LIỆU đã lưu trước khi sửa lỗi (phát hiện khi làm Giai đoạn 6, xem sửa
 * cùng lúc ở App\Services\Teacher\QuestionService::buildGradingConfig() +
 * resources/views/teacher/questions/create.blade.php): trước đây form Teacher tạo/sửa câu
 * Trắc nghiệm gửi lên 'correct_option' dạng CHỮ CÁI ("A"/"B"/"C"/"D") và lưu THẲNG vào
 * grading_config['correct_options'] mà KHÔNG đổi sang chỉ số — trong khi App\Services\
 * AttemptService::gradeMcq() so khớp bằng array_map('intval', ...), và intval("B")/("C")/
 * ("D") đều ra 0. Hậu quả: mọi câu Trắc nghiệm giáo viên tự tạo tay (KHÔNG qua OCR, luồng OCR
 * vẫn đúng vì có DocumentImportService::letterToIndex()) có đáp án đúng KHÁC phương án A đều
 * bị chấm SAI cho học sinh chọn đúng đáp án thật.
 *
 * Lệnh này quét TOÀN BỘ câu Trắc nghiệm, tìm đúng những câu 'correct_options' còn lưu dạng
 * CHỮ CÁI (không phải số) rồi đổi lại thành chỉ số (A→0, B→1, C→2, D→3) — AN TOÀN chạy lại
 * nhiều lần (idempotent: câu đã đúng dạng số thì bỏ qua, không đụng vào). Chạy thử xem trước
 * bằng --dry-run (không ghi gì), bỏ cờ đó để ghi thật.
 *
 * Cách chạy (server thật, sau khi deploy bản sửa lỗi này):
 *   php artisan app:fix-mcq-correct-option-letters --dry-run   (xem trước, không đổi gì)
 *   php artisan app:fix-mcq-correct-option-letters             (ghi thật)
 */
Artisan::command('app:fix-mcq-correct-option-letters {--dry-run}', function () {
    $letterToIndex = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
    $dryRun = (bool) $this->option('dry-run');

    $fixedCount = 0;
    $skippedUnrecognized = 0;

    Question::where('type', 'mcq')->chunkById(200, function ($questions) use (&$fixedCount, &$skippedUnrecognized, $letterToIndex, $dryRun) {
        foreach ($questions as $question) {
            $config = $question->grading_config ?? [];
            $correctOptions = $config['correct_options'] ?? [];

            if ($correctOptions === [] || is_numeric($correctOptions[0] ?? null)) {
                continue; // rỗng hoặc đã đúng dạng số — không cần sửa.
            }

            $letter = strtoupper((string) $correctOptions[0]);

            if (! isset($letterToIndex[$letter])) {
                $this->warn("Câu #{$question->id}: correct_options=\"{$correctOptions[0]}\" không nhận diện được (không phải A/B/C/D) — bỏ qua, cần xem tay.");
                $skippedUnrecognized++;
                continue;
            }

            $this->line("Câu #{$question->id} \"{$question->title}\": \"{$letter}\" -> {$letterToIndex[$letter]}");

            if (! $dryRun) {
                $config['correct_options'] = [$letterToIndex[$letter]];
                $question->update(['grading_config' => $config]);
            }

            $fixedCount++;
        }
    });

    $this->info(($dryRun ? '[DRY RUN] Sẽ sửa' : 'Đã sửa')." {$fixedCount} câu Trắc nghiệm.");
    if ($skippedUnrecognized > 0) {
        $this->warn("{$skippedUnrecognized} câu không nhận diện được định dạng — cần Admin xem tay.");
    }
})->purpose('Vá dữ liệu correct_options dạng chữ cái (A/B/C/D) về đúng chỉ số (0-3) cho câu Trắc nghiệm giáo viên tự tạo tay');
