<?php

use App\Models\Question;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
                continue;
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
