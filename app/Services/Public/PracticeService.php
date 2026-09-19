<?php

namespace App\Services\Public;

use App\Enums\QuestionType;
use App\Models\AssessmentItem;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Support\PracticeFilters;
use App\Support\QuestionDifficulty;


class PracticeService
{
    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private TagRepositoryInterface $tags,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function indexData(?User $viewer): array
    {
        $assessments = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->withCount(['items', 'answerKeys', 'codingItems'])
            ->latest()
            ->limit(30)
            ->get();

        $codingAssessmentIds = $this->assessmentIdsWithCoding($assessments->pluck('id')->all());

        $progressByAssessment = $viewer !== null
            ? $this->attempts->progressForUserAndAssessments($viewer->id, $assessments->pluck('id')->all())->keyBy('assessment_id')
            : collect();

        $items = $assessments->map(function ($a) use ($codingAssessmentIds, $progressByAssessment) {
            $row = $progressByAssessment->get($a->id);
            $best = $row !== null && $row->best_score !== null ? (float) $row->best_score : null;
            $total = (float) ($a->total_points ?: 0);
            $submitted = $row !== null ? (int) $row->submitted_count : 0;
            $inProgress = $row !== null && (int) $row->in_progress_count > 0;

            if ($submitted > 0) {
                $progressStatus = 'done';
                $progress = $best !== null && $total > 0 ? (int) round(min(100, max(0, $best / $total * 100))) : 100;
                $progressLabel = $best !== null ? 'Đã nộp · '.rtrim(rtrim(number_format($best, 2, ',', ''), '0'), ',').' điểm' : 'Đã nộp';
            } elseif ($inProgress) {
                $progressStatus = 'doing';
                $progress = 35;
                $progressLabel = 'Đang làm dở';
            } else {
                $progressStatus = 'open';
                $progress = 0;
                $progressLabel = 'Chưa làm';
            }

            return [
                'id' => $a->id,
                'title' => $a->title,
                'itemsCount' => $a->items_count > 0 ? $a->items_count : (int) ($a->answer_keys_count ?? 0),
                'totalPoints' => $a->total_points,
                'durationMinutes' => $a->duration_minutes,
                'hasCoding' => $codingAssessmentIds->contains($a->id) || (int) ($a->coding_items_count ?? 0) > 0,
                'progressStatus' => $progressStatus,
                'progress' => $progress,
                'progressLabel' => $progressLabel,
            ];
        })->all();

        return array_merge([
            'items' => $items,
            'problems' => $this->problemRows($viewer),
            'canTakeDirectly' => $viewer !== null && $viewer->hasRole(Role::STUDENT),
        ], PracticeFilters::options($this->tags));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function problemRows(?User $viewer): array
    {
        $questions = Question::query()
            ->where('status', 'published')
            ->whereNull('product_id')
            ->whereIn('type', array_keys(PracticeFilters::TYPE_META))
            ->with(['tags:id,name'])
            ->latest()
            ->limit(60)
            ->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $questionIds = $questions->pluck('id')->all();

        $stats = AttemptAnswer::query()
            ->selectRaw('question_id, COUNT(*) as submissions, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as accepted', ['accepted'])
            ->whereIn('question_id', $questionIds)
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        $mine = collect();
        if ($viewer !== null) {
            $selectMine = 'question_id, COUNT(*) as mine, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as mine_accepted';

            if (AttemptAnswer::supportsTestCounts()) {
                $selectMine .= ', MAX(passed_tests) as mine_passed_tests, MAX(total_tests) as mine_total_tests';
            }

            $mine = AttemptAnswer::query()
                ->selectRaw($selectMine, ['accepted'])
                ->whereIn('question_id', $questionIds)
                ->whereHas('attempt', fn ($q) => $q->where('user_id', $viewer->id))
                ->groupBy('question_id')
                ->get()
                ->keyBy('question_id');
        }

        return $questions->map(function (Question $q) use ($stats, $mine) {
            $row = $stats->get($q->id);
            $submissions = (int) ($row->submissions ?? 0);
            $accepted = (int) ($row->accepted ?? 0);
            $rate = $submissions > 0 ? round($accepted / $submissions * 100, 1) : 0.0;

            $mineRow = $mine->get($q->id);
            $mineCount = (int) ($mineRow->mine ?? 0);
            $mineAccepted = (int) ($mineRow->mine_accepted ?? 0);

            $minePassed = $mineRow->mine_passed_tests ?? null;
            $mineTotalTests = $mineRow->mine_total_tests ?? null;
            $mineTestPercent = ($mineTotalTests !== null && (int) $mineTotalTests > 0 && $minePassed !== null)
                ? (int) round((int) $minePassed / (int) $mineTotalTests * 100)
                : null;

            $status = 'todo';
            if ($mineAccepted > 0) {
                $status = 'ac';
            } elseif ($mineCount > 0) {
                $status = 'doing';
            }

            $meta = $q->metadata ?? [];

            $difficultyKey = QuestionDifficulty::resolve($meta, (int) $q->points);

            $limits = $q->grading_config['limits'] ?? [];

            return [
                'id' => $q->id,
                'code' => $q->code,
                'title' => $q->title,
                'typeKey' => $q->type instanceof QuestionType ? $q->type->value : (string) $q->type,
                'typeLabel' => PracticeFilters::TYPE_META[$q->type instanceof QuestionType ? $q->type->value : (string) $q->type]['label'] ?? 'Câu hỏi',
                'tagIds' => $q->tags->pluck('id')->all(),
                'topicLabel' => $q->tags->first()?->name ?? 'Chưa gắn chuyên đề',
                'difficulty' => $difficultyKey,
                'difficultyLevel' => QuestionDifficulty::stars($difficultyKey),
                'difficultyLabel' => QuestionDifficulty::label($difficultyKey),
                'points' => (int) $q->points,
                'timeLimit' => isset($limits['time_ms']) ? round($limits['time_ms'] / 1000, 1).'s' : '—',
                'memoryLimit' => isset($limits['memory_mb']) ? $limits['memory_mb'].'MB' : '—',
                'submissionCount' => $submissions,
                'acceptedCount' => $accepted,
                'acRate' => $rate,
                'userSubmissions' => $mineCount,
                'minePassedTests' => $minePassed !== null ? (int) $minePassed : null,
                'mineTotalTests' => $mineTotalTests !== null ? (int) $mineTotalTests : null,
                'mineTestPercent' => $mineTestPercent,
                'status' => $status,
                'subject' => $q->subject,
                'subjectLabel' => $q->subjectLabel(),
                'grade' => $q->grade,
            ];
        })->values()->all();
    }

    /** @param array<int, int> $assessmentIds
     * @return \Illuminate\Support\Collection<int, int> */
    private function assessmentIdsWithCoding(array $assessmentIds): \Illuminate\Support\Collection
    {
        if ($assessmentIds === []) {
            return collect();
        }

        return AssessmentItem::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereHas('question', fn ($q) => $q->where('type', 'coding'))
            ->distinct()
            ->pluck('assessment_id');
    }
}
