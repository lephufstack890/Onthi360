<?php

namespace App\Services\Public;

use App\Enums\AttemptStatus;
use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\AssessmentAnswerKey;
use App\Models\AssessmentCodingItem;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptAnswerKey;
use App\Models\AttemptCodingItem;
use App\Models\PracticeAssignment;
use App\Models\Question;
use App\Models\User;
use App\Support\PracticeActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * SỬA 7/10 (khách: "giáo viên ... xem nhật ký nộp bài của học sinh. Giao diện nhật ký trong source
 * mới check và dựng cho tôi") — dữ liệu cho trang NHẬT KÝ NỘP BÀI, dựng theo
 * education-main/src/components/SubmissionHistoryPage.jsx.
 *
 * Bản mẫu lưu lượt nộp trong localStorage nên ai cũng "thấy" được; ở đây là dữ liệu của NHIỀU
 * người nên phải có luật quyền xem rõ ràng (xem visibleUserIds()):
 *   · admin             : mọi lượt nộp;
 *   · giáo viên         : lượt nộp của chính mình + của các học sinh MÌNH đã giao bài/đề này;
 *   · học sinh/người khác: chỉ lượt nộp của chính mình.
 * Không để lộ tên + bài làm của người lạ ra cho bất kỳ ai đăng nhập.
 *
 * "Bài mẫu" (SỬA 7/10): ADMIN chỉ định một lượt nộp làm bài mẫu — xem PracticeSampleService.
 * "Hoạt động" (SỬA 7/10): cột + dòng thời gian các dấu hiệu lúc làm bài (rời tab, phím chụp màn hình,
 * mở Hướng dẫn/Bài mẫu) — CHỈ ADMIN xem. Trình duyệt gửi nhật ký kèm lúc nộp bài, lưu ở
 * attempt_answers.activity_log (xem App\Support\PracticeActivityLog). Đề thi chưa có dữ liệu này.
 */
class PracticeHistoryService
{
    /** Trần số lượt nộp đưa ra trang, để trang không phình vô hạn (mới nhất trước). */
    private const LIMIT = 500;

    /** Cắt bài làm đưa ra khung chi tiết cho gọn (ký tự). */
    private const CODE_CHARS = 8000;

    public function __construct(
        private PracticeAssignmentService $assignments,
        private PracticeSampleService $samples,
    ) {}

    /** Có được mở nhật ký không: cần đăng nhập. Khách chưa đăng nhập bị chuyển sang trang đăng nhập. */
    public function mayView(?User $viewer): bool
    {
        return $viewer !== null;
    }

    /**
     * Danh sách id người dùng mà $viewer được xem lượt nộp. null = không giới hạn (admin).
     *
     * @return list<int>|null
     */
    public function visibleUserIds(User $viewer, string $type, int $subjectId): ?array
    {
        if ($this->assignments->isAdmin($viewer)) {
            return null;
        }

        $ids = [$viewer->id];

        if ($this->assignments->isTeacher($viewer) && $this->assignments->isReady()) {
            $ids = array_merge(
                $ids,
                PracticeAssignment::query()
                    ->where('assigned_by', $viewer->id)
                    ->where('type', $type)
                    ->where('subject_id', $subjectId)
                    ->pluck('student_id')
                    ->all()
            );
        }

        return array_values(array_unique($ids));
    }

    // ───────────────────────── Bài tập ─────────────────────────

    /** @return array<string, mixed> */
    public function problemData(User $viewer, int $questionId): array
    {
        $question = Question::query()
            ->where('status', 'published')
            ->findOrFail($questionId);

        $max = (float) $question->points;
        $visible = $this->visibleUserIds($viewer, PracticeAssignment::TYPE_PROBLEM, $questionId);

        $answers = AttemptAnswer::query()
            ->with(['attempt:id,user_id,started_at', 'attempt.user:id,name,email,phone'])
            ->where('question_id', $questionId)
            ->where(function ($w) {
                $w->where('submission_count', '>', 0)
                    ->orWhereNotNull('graded_at')
                    ->orWhereNotNull('score');
            })
            ->whereHas('attempt', function ($a) use ($visible) {
                if ($visible !== null) {
                    $a->whereIn('user_id', $visible);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get();

        // SỬA 7/10 — cột "Hoạt động" (rời tab, phím chụp màn hình, mở Hướng dẫn/Bài mẫu…) CHỈ ADMIN xem:
        // đó là dữ liệu giám sát, giáo viên/học sinh không được thấy. Kiểm ở máy chủ và KHÔNG đưa
        // dữ liệu vào trang của người khác, chứ không chỉ ẩn cột bằng giao diện.
        $showActivity = $this->canSeeActivity($viewer);

        $rows = $answers->map(function (AttemptAnswer $a) use ($viewer, $max, $showActivity) {
            $user = $a->attempt?->user;
            $final = $a->verdict instanceof VerdictStatus ? $a->verdict->isFinal() : true;
            $score = $a->score !== null ? (float) $a->score : null;

            $result = ! $final
                ? 'pending'
                : $this->resultOf($score, $max, $a->verdict === VerdictStatus::Accepted);

            $submitted = $a->updated_at;

            return [
                'id' => 'A'.$a->id,
                'userId' => $a->attempt?->user_id,
                'mine' => $a->attempt?->user_id === $viewer->id,
                'submitter' => $user?->name ?? 'Người dùng đã xoá',
                'account' => $user?->email ?: ($user?->phone ?: ''),
                'submittedAt' => $submitted?->format('H:i:s'),
                'submittedDate' => $submitted?->format('d/m/Y'),
                'submittedDay' => $submitted?->format('Y-m-d'),
                'submittedTs' => $submitted?->getTimestamp() ?? 0,
                'startedAt' => $a->attempt?->started_at?->format('H:i:s d/m/Y'),
                'gradedAt' => $final ? ($a->graded_at ?? $submitted)?->format('H:i:s d/m/Y') : null,
                'result' => $result,
                'verdictLabel' => $final && $a->verdict instanceof VerdictStatus ? $a->verdict->label() : null,
                'score' => $final ? $score : null,
                'maxScore' => $max,
                'language' => $a->language,
                'tests' => (AttemptAnswer::supportsTestCounts() && ($a->total_tests ?? 0) > 0)
                    ? (int) $a->passed_tests.'/'.(int) $a->total_tests.' test'
                    : null,
                'attempts' => max(1, (int) $a->submission_count),
                'response' => $a->code_source !== null
                    ? mb_substr((string) $a->code_source, 0, self::CODE_CHARS)
                    : (is_array($a->answer) ? json_encode($a->answer, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null),
                // SỬA 7/10 — nút "Chỉ định bài mẫu" chỉ bật khi lượt nộp CÓ nội dung bài làm.
                'hasResponse' => $this->samples->responseOf($a) !== null,
            ] + ($showActivity ? $this->activityOf($a) : []);
        })->values()->all();

        $data = $this->envelope($viewer, 'problem', [
            'id' => $question->id,
            'title' => $question->title,
            'code' => $question->code,
            'backHref' => route('practice.index'),
        ], $rows, $visible);

        // SỬA 7/10 (khách: "admin chỉ định bài mẫu, chỉ admin mới có quyền") — lượt nộp đang là bài mẫu
        // + quyền chỉ định (chỉ admin). Chỉ có ở BÀI TẬP, đề thi không có bài mẫu kiểu này.
        $sample = $this->samples->forQuestion($question->id);
        $data['sampleId'] = $sample?->attempt_answer_id ? 'A'.$sample->attempt_answer_id : null;
        $data['canDesignate'] = $this->samples->canDesignate($viewer);
        $data['sampleUrl'] = $data['canDesignate'] ? route('practice.history.sample.store', $question->id) : '';
        $data['sampleClearUrl'] = $data['canDesignate'] ? route('practice.history.sample.destroy', $question->id) : '';
        $data['canSeeActivity'] = $showActivity;
        $data['activityCategories'] = $showActivity ? PracticeActivityLog::CATEGORIES : [];

        return $data;
    }

    /** Chỉ admin xem được cột Hoạt động (chỉ có ở bài tập; đề thi chưa ghi nhật ký về máy chủ). */
    public function canSeeActivity(?User $viewer): bool
    {
        return $this->assignments->isAdmin($viewer);
    }

    /**
     * Dữ liệu cột Hoạt động của một lượt nộp: số dòng theo nhóm dấu hiệu + các mốc để vẽ dòng thời gian.
     * Mỗi lượt chỉ đưa tối đa 100 mốc MỚI NHẤT ra trang để bảng không phình; eventCount là tổng thật.
     *
     * @return array{signals: array<string,int>, flagged: bool, eventCount: int, events: list<array<string,mixed>>}
     */
    private function activityOf(AttemptAnswer $a): array
    {
        $log = AttemptAnswer::supportsActivityLog() && is_array($a->activity_log) ? $a->activity_log : [];
        $signals = PracticeActivityLog::counts($log);
        $tz = config('app.timezone');

        $events = array_map(function (array $event) use ($tz) {
            $at = null;
            if (! empty($event['occurredAt'])) {
                try {
                    $at = Carbon::parse($event['occurredAt'])->setTimezone($tz);
                } catch (\Throwable) {
                    $at = null;
                }
            }

            return [
                'id' => (string) ($event['id'] ?? ''),
                'category' => PracticeActivityLog::category($event),
                'title' => (string) ($event['title'] ?? ''),
                'detail' => (string) ($event['detail'] ?? ''),
                'time' => $at?->format('H:i:s'),
                'date' => $at?->format('d/m/Y'),
            ];
        }, array_slice(array_values($log), -100));

        return [
            'signals' => $signals,
            'flagged' => $signals !== [],
            'eventCount' => count($log),
            'events' => $events,
        ];
    }

    // ───────────────────────── Đề thi ─────────────────────────

    /** @return array<string, mixed> */
    public function examData(User $viewer, int $assessmentId): array
    {
        $exam = Assessment::query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->with(['items.question:id,title,points'])
            ->findOrFail($assessmentId);

        $itemsSum = (float) $exam->items->sum(fn ($i) => (float) ($i->points_override ?? $i->question?->points ?? 0));
        $max = (float) $exam->total_points ?: $itemsSum;

        $visible = $this->visibleUserIds($viewer, PracticeAssignment::TYPE_EXAM, $assessmentId);

        $attempts = Attempt::query()
            ->with('user:id,name,email,phone')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('submitted_at')
            ->when($visible !== null, fn ($q) => $q->whereIn('user_id', $visible))
            ->orderByDesc('submitted_at')
            ->limit(self::LIMIT)
            ->get();

        $breakdown = $this->examBreakdown($exam, $attempts);

        $rows = $attempts->map(function (Attempt $a) use ($viewer, $max, $breakdown) {
            $user = $a->user;
            $score = $a->total_score !== null ? (float) $a->total_score : null;
            $pending = $a->is_provisional || $score === null || $a->status === AttemptStatus::Grading;

            $seconds = $a->started_at !== null
                ? max(0, (int) $a->submitted_at->diffInSeconds($a->started_at, true))
                : null;

            return [
                'id' => 'L'.$a->id,
                'userId' => $a->user_id,
                'mine' => $a->user_id === $viewer->id,
                'submitter' => $user?->name ?? 'Người dùng đã xoá',
                'account' => $user?->email ?: ($user?->phone ?: ''),
                'submittedAt' => $a->submitted_at->format('H:i:s'),
                'submittedDate' => $a->submitted_at->format('d/m/Y'),
                'submittedDay' => $a->submitted_at->format('Y-m-d'),
                'submittedTs' => $a->submitted_at->getTimestamp(),
                'startedAt' => $a->started_at?->format('H:i:s d/m/Y'),
                'gradedAt' => $pending ? null : $a->submitted_at->format('H:i:s d/m/Y'),
                'duration' => $seconds !== null ? intdiv($seconds, 60).' phút '.($seconds % 60).' giây' : null,
                'result' => $pending ? 'pending' : $this->resultOf($score, $max, false),
                'verdictLabel' => null,
                'score' => $pending ? null : $score,
                'maxScore' => $max,
                'items' => $breakdown[$a->id] ?? [],
            ];
        })->values()->all();

        return $this->envelope($viewer, 'exam', [
            'id' => $exam->id,
            'title' => $exam->title,
            'code' => $exam->exam_code ?: '#'.$exam->id,
            'backHref' => route('practice.index', ['tab' => 'de-thi']),
        ], $rows, $visible);
    }

    /**
     * Điểm từng câu / từng phần của mỗi lượt thi, gom từ 3 nguồn (đề cấu trúc, bài lập trình con
     * và phiếu trả lời của đề PDF).
     *
     * @param  Collection<int, Attempt>  $attempts
     * @return array<int, list<array<string, mixed>>>  khoá = attempt id
     */
    private function examBreakdown(Assessment $exam, Collection $attempts): array
    {
        if ($attempts->isEmpty()) {
            return [];
        }

        $ids = $attempts->pluck('id')->all();
        $out = array_fill_keys($ids, []);

        // 1) Câu hỏi của đề cấu trúc.
        $itemMeta = [];
        foreach ($exam->items->sortBy('order') as $i => $item) {
            $itemMeta[$item->question_id] = [
                'no' => $i + 1,
                'title' => $item->question?->title ?? 'Câu '.($i + 1),
                'max' => (float) ($item->points_override ?? $item->question?->points ?? 0),
            ];
        }

        if ($itemMeta !== []) {
            AttemptAnswer::query()
                ->whereIn('attempt_id', $ids)
                ->whereIn('question_id', array_keys($itemMeta))
                ->get()
                ->each(function (AttemptAnswer $a) use (&$out, $itemMeta) {
                    $m = $itemMeta[$a->question_id];
                    $final = $a->verdict instanceof VerdictStatus ? $a->verdict->isFinal() : true;
                    $score = $a->score !== null ? (float) $a->score : null;

                    $out[$a->attempt_id][] = [
                        'order' => $m['no'],
                        'label' => 'Câu '.$m['no'].': '.$m['title'],
                        'score' => $final ? $score : null,
                        'max' => $m['max'],
                        'result' => $final ? $this->resultOf($score, $m['max'], $a->verdict === VerdictStatus::Accepted) : 'pending',
                        'tests' => (AttemptAnswer::supportsTestCounts() && ($a->total_tests ?? 0) > 0)
                            ? (int) $a->passed_tests.'/'.(int) $a->total_tests.' test' : null,
                        'response' => $a->code_source !== null ? mb_substr((string) $a->code_source, 0, self::CODE_CHARS) : null,
                        'language' => $a->language,
                    ];
                });
        }

        // 2) Bài lập trình con trong đề PDF.
        $codingMeta = AssessmentCodingItem::query()->where('assessment_id', $exam->id)->get()->keyBy('id');
        if ($codingMeta->isNotEmpty()) {
            AttemptCodingItem::query()
                ->whereIn('attempt_id', $ids)
                ->get()
                ->each(function (AttemptCodingItem $c) use (&$out, $codingMeta) {
                    $m = $codingMeta->get($c->coding_item_id);
                    if ($m === null) {
                        return;
                    }
                    $final = $c->verdict instanceof VerdictStatus ? $c->verdict->isFinal() : true;
                    $score = $c->score !== null ? (float) $c->score : null;
                    $max = (float) $m->points;

                    $out[$c->attempt_id][] = [
                        'order' => 1000 + $c->coding_item_id,
                        'label' => ($m->code ? $m->code.': ' : '').$m->title,
                        'score' => $final ? $score : null,
                        'max' => $max,
                        'result' => $final ? $this->resultOf($score, $max, $c->verdict === VerdictStatus::Accepted) : 'pending',
                        'tests' => (AttemptCodingItem::supportsTestCounts() && ($c->total_tests ?? 0) > 0)
                            ? (int) $c->passed_tests.'/'.(int) $c->total_tests.' test' : null,
                        'response' => $c->code_source !== null ? mb_substr((string) $c->code_source, 0, self::CODE_CHARS) : null,
                        'language' => $c->language,
                    ];
                });
        }

        // 3) Phiếu trả lời của đề PDF — gộp thành MỘT dòng "N/M câu đúng".
        $keyMeta = AssessmentAnswerKey::query()->where('assessment_id', $exam->id)->get(['id', 'points']);
        if ($keyMeta->isNotEmpty()) {
            $keyMax = (float) $keyMeta->sum('points');
            AttemptAnswerKey::query()
                ->whereIn('attempt_id', $ids)
                ->get()
                ->groupBy('attempt_id')
                ->each(function ($group, $attemptId) use (&$out, $keyMeta, $keyMax) {
                    $correct = $group->where('is_correct', true)->count();
                    $score = (float) $group->sum(fn ($g) => (float) ($g->score ?? 0));

                    $out[$attemptId][] = [
                        'order' => 2000,
                        'label' => 'Phiếu trả lời trắc nghiệm',
                        'score' => $score,
                        'max' => $keyMax,
                        'result' => $this->resultOf($score, $keyMax, false),
                        'tests' => $correct.'/'.$keyMeta->count().' câu đúng',
                        'response' => null,
                        'language' => null,
                    ];
                });
        }

        foreach ($out as $attemptId => $list) {
            usort($list, fn ($a, $b) => $a['order'] <=> $b['order']);
            $out[$attemptId] = $list;
        }

        return $out;
    }

    // ───────────────────────── Dùng chung ─────────────────────────

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>|null  $visible
     * @return array<string, mixed>
     */
    private function envelope(User $viewer, string $type, array $subject, array $rows, ?array $visible): array
    {
        $role = $this->assignments->isAdmin($viewer) ? 'admin' : ($this->assignments->isTeacher($viewer) ? 'teacher' : 'student');

        return [
            'type' => $type,
            'subject' => $subject,
            'rows' => $rows,
            'role' => $role,
            // Có xem được lượt nộp của người KHÁC không — quyết định có hiện nút "Chỉ bài của tôi".
            'canSeeOthers' => $visible === null || count($visible) > 1,
            'viewerName' => $viewer->name,
            'truncated' => count($rows) >= self::LIMIT,
        ];
    }

    /** ac / partial / wa theo điểm; accepted luôn là ac. */
    private function resultOf(?float $score, float $max, bool $accepted): string
    {
        if ($accepted) {
            return 'ac';
        }

        if ($score === null || $max <= 0) {
            return $score !== null && $score > 0 ? 'partial' : 'wa';
        }

        if ($score >= $max - 0.005) {
            return 'ac';
        }

        return $score > 0 ? 'partial' : 'wa';
    }
}
