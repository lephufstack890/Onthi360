<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use Illuminate\Console\Command;

/**
 * SỬA 23/9 (khách báo "30.00 / 10 · 300% số điểm") — tính lại assessments.total_points cho
 * các đề CÓ CÂU HỎI RỜI, vì trước đây ô "Tổng điểm" trên form Sửa đề ghi đè con số tự tính
 * (xem Admin\ContentService::assessmentUpdate()). Đề đã lỡ bị đạp sai thì chạy lệnh này 1 lần:
 *
 *   php artisan assessment:recalc-points --dry-run     (chỉ xem, không ghi)
 *   php artisan assessment:recalc-points               (ghi lại cho mọi đề lệch)
 *   php artisan assessment:recalc-points --id=12       (chỉ 1 đề)
 *
 * Không đụng đề PDF (đề PDF không có assessment_items, điểm do PdfAssessmentEditingService
 * tính riêng) và không đụng bài làm cũ — điểm lượt làm cũ giữ nguyên, chỉ MẪU SỐ của đề đúng lại.
 */
class AssessmentRecalcPoints extends Command
{
    protected $signature = 'assessment:recalc-points
        {--id= : Chỉ tính lại 1 đề}
        {--dry-run : Chỉ in ra, không ghi}';

    protected $description = 'Chốt điểm từng câu trong đề và tính lại tổng điểm của đề.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $query = Assessment::query()->with('items.question');
        if (filled($this->option('id'))) {
            $query->whereKey((int) $this->option('id'));
        }

        $changed = 0;
        $checked = 0;

        $query->chunk(100, function ($assessments) use (&$changed, &$checked, $dry) {
            foreach ($assessments as $assessment) {
                if ($assessment->items->isEmpty()) {
                    continue;
                }

                $checked++;

                /*
                 * SỬA 23/9 (khách: "nhập điểm cho từng câu") — CHỐT ĐIỂM VÀO ĐỀ.
                 *
                 * Các đề cũ có assessment_items.points_override = null, nghĩa là lúc chấm mới
                 * đi đọc questions.points. Sửa điểm gốc của một câu trong kho (cho đề khác) là
                 * điểm của mọi đề cũ dùng câu đó âm thầm đổi theo. Ghi hẳn số hiện tại vào đề
                 * thì từ nay đề nào chốt điểm của đề đó.
                 */
                $filled = 0;
                foreach ($assessment->items as $item) {
                    if ($item->points_override !== null) {
                        continue;
                    }

                    $points = max(1, (int) ($item->question?->points ?? 1));

                    if (! $dry) {
                        $item->forceFill(['points_override' => $points])->save();
                    }

                    $item->points_override = $points;
                    $filled++;
                }

                if ($filled > 0) {
                    $this->line(sprintf('Đề #%d "%s": chốt điểm cho %d câu chưa có điểm riêng.', $assessment->id, $assessment->title, $filled));
                }

                $derived = (int) $assessment->items->sum(
                    fn ($item) => (int) ($item->points_override ?? $item->question?->points ?? 0)
                );

                if ((int) $assessment->total_points === $derived) {
                    if ($filled > 0) {
                        $changed++;
                    }

                    continue;
                }

                $this->line(sprintf(
                    'Đề #%d "%s": %s → %d điểm (%d câu)',
                    $assessment->id,
                    $assessment->title,
                    $assessment->total_points,
                    $derived,
                    $assessment->items->count()
                ));

                if (! $dry) {
                    $assessment->forceFill(['total_points' => $derived])->save();
                }

                $changed++;
            }
        });

        $this->info(sprintf(
            '%s %d/%d đề có câu hỏi rời.',
            $dry ? 'Sẽ sửa' : 'Đã sửa',
            $changed,
            $checked
        ));

        return self::SUCCESS;
    }
}
