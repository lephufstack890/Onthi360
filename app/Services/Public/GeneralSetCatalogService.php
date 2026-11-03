<?php

namespace App\Services\Public;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\User;
use App\Support\QuestionDifficulty;

/**
 * SỬA 3/11 (khách: "Luyện tập > Bài tập: thêm dạng Phổ thông — mỗi bài nhiều câu trắc nghiệm / điền từ…") — danh sách
 * "Bài phổ thông" (đề Luyện tập ĐÃ phát hành, practice_kind = general) cho trang Luyện tập khi chọn dạng bài "Phổ thông"
 * (partials/practice-general-sets). Chỉ ĐỌC; không đụng danh sách bài lập trình hay tab Đề thi.
 */
class GeneralSetCatalogService
{
    private const LIMIT = 300;

    /** @return list<array<string, mixed>> */
    /** SỬA 3/11 (khách: "tổng số bài ngoài Luyện tập / trang chủ phải tính cả bài phổ thông") — số bài đang hiện ở trang Luyện tập. */
    public static function publishedCount(): int
    {
        if (! Assessment::supportsPracticeKind()) {
            return 0;
        }

        return min(self::LIMIT, Assessment::query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->where('practice_kind', Assessment::PRACTICE_KIND_GENERAL)
            ->when(\App\Services\ExamBundle\ExamBundleService::ready(), fn ($q) => $q->where('bundle_only', false))
            ->count());
    }

    public function rows(?User $viewer): array
    {
        if (! Assessment::supportsPracticeKind()) {
            return [];
        }

        $sets = Assessment::query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->where('practice_kind', Assessment::PRACTICE_KIND_GENERAL)
            ->when(\App\Services\ExamBundle\ExamBundleService::ready(), fn ($q) => $q->where('bundle_only', false))
            ->with(['items.question.tags', 'creator:id,name'])
            ->latest()
            ->limit(self::LIMIT)
            ->get();
        if ($sets->isEmpty()) {
            return [];
        }
        $ids = $sets->pluck('id')->all();

        // Lượt đã nộp toàn hệ thống (bỏ lượt làm thử / luyện lại của ban tổ chức — source staff_test).
        $stats = Attempt::query()
            ->selectRaw('assessment_id, COUNT(*) as n, AVG(total_score) as avg_score')
            ->whereIn('assessment_id', $ids)
            ->whereNotNull('submitted_at')
            ->where('status', '!=', AttemptStatus::InProgress->value)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'staff_test'))
            ->groupBy('assessment_id')
            ->get()
            ->keyBy('assessment_id');

        $mine = collect();
        if ($viewer !== null) {
            $mine = Attempt::query()
                ->selectRaw('assessment_id, COUNT(*) as n, MAX(total_score) as best')
                ->where('user_id', $viewer->id)
                ->whereIn('assessment_id', $ids)
                ->whereNotNull('submitted_at')
                ->where('status', '!=', AttemptStatus::InProgress->value)
                ->groupBy('assessment_id')
                ->get()
                ->keyBy('assessment_id');
        }

        $typeLabels = ['mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền từ', 'composite' => 'Câu nhiều ý'];
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');

        return $sets->map(function (Assessment $a) use ($stats, $mine, $typeLabels, $num) {
            $questions = $a->items->pluck('question')->filter();
            $tags = $questions->flatMap(fn ($q) => $q->tags)->unique('id')->values();
            $types = [];
            foreach ($questions as $q) {
                $t = $q->type instanceof \BackedEnum ? $q->type->value : (string) $q->type;
                $label = $typeLabels[$t] ?? 'Khác';
                $types[$label] = ($types[$label] ?? 0) + 1;
            }
            $total = (float) $a->total_points;
            $s = $stats->get($a->id);
            $m = $mine->get($a->id);
            $stars = $a->difficultyStars();
            $diffKey = $stars !== null ? (array_search($stars, QuestionDifficulty::STARS, true) ?: 'none') : 'none';
            $avgPct = ($s !== null && $total > 0 && $s->avg_score !== null) ? round((float) $s->avg_score / $total * 100) : null;

            return [
                'id' => (int) $a->id,
                'title' => (string) $a->title,
                'code' => (string) ($a->exam_code ?? ''),
                'subtitle' => $a->subtitle,
                'questionCount' => $questions->count(),
                'totalPoints' => $num($total),
                'typeSummary' => implode(' · ', array_map(fn ($l, $n) => $n.' '.$l, array_keys($types), $types)),
                'tagIds' => $tags->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'topicLabels' => $tags->pluck('name')->values()->all(),
                // SỬA 3/11 (khách: "hiển thị in đúc dạng lập trình") — các ô giống hàng bài lập trình.
                'topicLabel' => $tags->first()?->name ?? 'Chưa gắn chuyên đề',
                'provinceLabel' => $a->provinceLabel() ?? 'Toàn quốc',
                'regionLabel' => $a->regionLabel() ?? 'Tất cả các miền',
                'yearLabel' => filled($a->academic_year) ? (string) $a->academic_year : (string) ($a->created_at?->format('Y') ?? '—'),
                'yearNum' => (int) preg_replace('/\D+/', '', (string) ($a->academic_year ?: $a->created_at?->format('Y'))),
                'authorName' => (string) ($a->author ?: ($a->creator->name ?? '')),
                'difficulty' => $diffKey,
                'difficultyStars' => $stars ?? 0,
                'difficultyLabel' => $a->difficultyLabel() ?? 'Chưa xếp',
                'attemptCount' => (int) ($s->n ?? 0),
                'avgPercent' => $avgPct,
                'myAttempts' => (int) ($m->n ?? 0),
                'myBest' => $m !== null && $m->best !== null ? $num($m->best).'/'.$num($total) : null,
                'search' => mb_strtolower(trim($a->title.' '.($a->exam_code ?? '').' '.$tags->pluck('name')->implode(' '))),
                'href' => route('practice.exam.show', $a->id),
            ];
        })->values()->all();
    }
}
