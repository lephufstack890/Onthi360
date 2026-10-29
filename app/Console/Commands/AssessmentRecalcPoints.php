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
