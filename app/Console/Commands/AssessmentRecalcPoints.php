<?php

namespace App\Console\Commands;

use App\Models\Assessment;
use Illuminate\Console\Command;

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
