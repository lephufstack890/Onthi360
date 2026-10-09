<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use App\Support\QuestionDifficulty;
use Illuminate\Console\Command;

class AssessmentRecalcPoints extends Command
{
    protected $signature = 'assessment:recalc-points
        {--id= : Chỉ tính lại 1 đề}
        {--theo-do-kho : Ghi ĐÈ điểm mọi câu theo độ khó của câu (mặc định chỉ điền câu còn thiếu điểm)}
        {--dry-run : Chỉ in ra, không ghi}';

    protected $description = 'Chốt điểm từng câu trong đề và tính lại tổng điểm của đề.';

    /**
     * SỬA 11/10 — LƯU Ý: từ hôm nay điểm từng câu lại do người ra đề NHẬP TAY ở màn chọn câu
     * (có thể là số thập phân); lệnh này chỉ còn để điền câu THIẾU điểm. Cờ --theo-do-kho GHI ĐÈ
     * điểm đã nhập — đừng chạy nếu không chủ ý.
     *
     * (Ghi chú cũ) SỬA 1/10 (khách: "đừng cho nhập nhé mà tự động active điểm của các câu theo độ khó của câu
     * đó") — từ hôm nay màn chọn câu KHÔNG còn ô nhập điểm, điểm tính từ độ khó. Đề ĐÃ TẠO TRƯỚC
     * ĐÓ vẫn giữ nguyên điểm cũ đã chốt (cố ý: đổi điểm một đề học sinh đã làm là đổi kết quả
     * của họ). Cờ --theo-do-kho là lối để chốt lại hàng loạt khi khách muốn, chạy kèm --dry-run
     * để xem trước rồi mới ghi thật.
     */
    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $theoDoKho = (bool) $this->option('theo-do-kho');

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

                $filled = 0;
                foreach ($assessment->items as $item) {
                    // Mặc định chỉ đụng tới câu CHƯA có điểm riêng. Có --theo-do-kho thì ghi đè
                    // mọi câu theo độ khó (xem docblock ở handle()).
                    if ($item->points_override !== null && ! $theoDoKho) {
                        continue;
                    }

                    $points = $item->question !== null
                        ? QuestionDifficulty::pointsForQuestion($item->question->metadata, (int) $item->question->points)
                        : max(1, (float) $item->points_override);

                    if ($item->points_override !== null && abs((float) $item->points_override - $points) < 0.005) {
                        continue;
                    }

                    if (! $dry) {
                        $item->forceFill(['points_override' => $points])->save();
                    }

                    $item->points_override = $points;
                    $filled++;
                }

                if ($filled > 0) {
                    $this->line(sprintf(
                        'Đề #%d "%s": %s %d câu.',
                        $assessment->id,
                        $assessment->title,
                        $theoDoKho ? 'chốt lại điểm theo độ khó cho' : 'chốt điểm cho',
                        $filled,
                    ));
                }

                // SỬA 11/10 — điểm có thể là số thập phân: cộng float, so sánh theo sai số 0.005.
                $derived = \App\Support\AssessmentPoints::sum(
                    $assessment->items->map(fn ($item) => $item->points_override ?? $item->question?->points ?? 0)
                );

                if (abs((float) $assessment->total_points - $derived) < 0.005) {
                    if ($filled > 0) {
                        $changed++;
                    }

                    continue;
                }

                $this->line(sprintf(
                    'Đề #%d "%s": %s → %s điểm (%d câu)',
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
