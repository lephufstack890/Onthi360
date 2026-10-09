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

    // ───────────────────────── Nhật ký làm đề (bảng xếp hạng) ─────────────────────────

    /** Trần số lượt thi đưa ra trang nhật ký làm đề (mới nhất trước). */
    private const EXAM_LOG_LIMIT = 1500;

    /**
     * SỬA 9/10 (khách: "nút Nhật ký làm đề — trang ra như hình 3, UI trong source mới, làm logic luôn") —
     * dữ liệu cho trang NHẬT KÝ LÀM ĐỀ dựng theo education-main/ExamHistoryPage.jsx: bảng XẾP HẠNG
     * theo lượt làm tốt nhất của mỗi người, điểm từng bài, thống kê từng bài, mở rộng xem các lần nộp.
     *
     * QUYỀN XEM (khách chốt 9/10): CHỈ ADMIN thấy lượt nộp của mọi người; người khác (kể cả giáo viên)
     * chỉ nhận lượt nộp của CHÍNH MÌNH — máy chủ không gửi dữ liệu người khác ra trang. Riêng thứ hạng
     * của người xem trên toàn đề vẫn được tính ở máy chủ và gửi dưới dạng một con số (myRank).
     *
     * Sắp hạng/lọc/phân trang chạy ngay trên trình duyệt (xem exam-log.blade.php).
     *
     * @return array<string, mixed>
     */
    public function examLogData(User $viewer, int $assessmentId): array
    {
        $exam = Assessment::query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->with(['items.question:id,title,points'])
            ->findOrFail($assessmentId);

        $pdf = $exam->isPdfMode();

        // Cột điểm: đề cấu trúc = mỗi câu một cột; đề PDF = cột "Phiếu trả lời" + mỗi bài lập trình một cột.
        $columns = [];
        if (! $pdf) {
            foreach ($exam->items->sortBy('order') as $item) {
                $columns[] = [
                    'id' => 'q'.$item->question_id,
                    'title' => $item->question?->title ?? 'Câu hỏi',
                    'points' => (float) $item->effectivePoints(),
                ];
            }
        } else {
            $keys = $exam->answerKeys()->get(['id', 'points']);
            if ($keys->isNotEmpty()) {
                $columns[] = ['id' => 'k', 'title' => 'Phiếu trả lời trắc nghiệm', 'points' => (float) $keys->sum('points')];
            }
            foreach ($exam->codingItems()->get() as $c) {
                $columns[] = ['id' => 'c'.$c->id, 'title' => trim(($c->code ? $c->code.': ' : '').$c->title), 'points' => (float) $c->points];
            }
        }

        $columnSum = (float) array_sum(array_column($columns, 'points'));
        $max = (float) $exam->total_points > 0 ? (float) $exam->total_points : $columnSum;

        $isAdmin = $this->assignments->isAdmin($viewer);
        // SỬA 9/10 (khách: "chỉ admin xem được hết, còn lại chỉ xem của người đó") — người không phải admin
        // (kể cả giáo viên) chỉ nhận lượt nộp của chính mình; máy chủ không gửi lượt của người khác ra trang.
        $visible = $isAdmin ? null : [$viewer->id];

        $attempts = Attempt::query()
            ->with('user:id,name,email,phone')
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('submitted_at')
            ->when(! $isAdmin, fn ($q) => $q->where('user_id', $viewer->id))
            ->orderByDesc('submitted_at')
            ->limit(self::EXAM_LOG_LIMIT)
            ->get();

        $cells = $this->examLogCells($exam, $attempts, $pdf);

        $rows = $attempts->map(function (Attempt $a) use ($viewer, $max, $cells, $visible, $isAdmin) {
            $user = $a->user;
            $score = $a->total_score !== null ? (float) $a->total_score : null;
            $pending = $a->is_provisional || $score === null || $a->status === AttemptStatus::Grading;
            $mine = $a->user_id === $viewer->id;
            $canOpen = $visible === null || in_array($a->user_id, $visible, true);
            $account = $user?->email ?: ($user?->phone ?: '');

            $seconds = $a->started_at !== null
                ? max(0, (int) $a->submitted_at->diffInSeconds($a->started_at, true))
                : null;

            $items = [];
            $detail = [];
            foreach ($cells[$a->id] ?? [] as $colId => $cell) {
                $items[$colId] = ['score' => $cell['score'], 'max' => $cell['max'], 'status' => $cell['status']];
                if ($canOpen) {
                    $detail[$colId] = ['response' => $cell['response'], 'language' => $cell['language'], 'tests' => $cell['tests']];
                }
            }

            return [
                'id' => 'L'.$a->id,
                'userId' => $a->user_id,
                'mine' => $mine,
                'submitter' => $user?->name ?? 'Người dùng đã xoá',
                'account' => ($isAdmin || $mine) ? $account : $this->maskAccount($account),
                'submittedTs' => $a->submitted_at->getTimestamp(),
                'submittedAt' => $a->submitted_at->format('H:i d/m/Y'),
                'startedAt' => $a->started_at?->format('H:i:s d/m/Y'),
                'duration' => $seconds !== null ? intdiv($seconds, 60).' phút '.($seconds % 60).' giây' : null,
                'score' => $pending ? null : $score,
                'maxScore' => $max,
                'pending' => $pending,
                'items' => $items,
                'canOpen' => $canOpen,
                'detail' => $canOpen ? $detail : null,
            ];
        })->values()->all();

        return [
            'type' => 'exam',
            'subject' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'code' => $exam->exam_code ?: '#'.$exam->id,
                'duration' => $exam->duration_minutes ? $exam->duration_minutes.' phút' : null,
                'questions' => $columns,
                'maxScore' => $max,
                'backHref' => route('practice.index', ['tab' => 'de-thi']),
            ],
            'rows' => $rows,
            'role' => $isAdmin ? 'admin' : ($this->assignments->isTeacher($viewer) ? 'teacher' : 'student'),
            'myRank' => $isAdmin ? null : $this->examRankOf($viewer, $assessmentId),
            'viewerId' => $viewer->id,
            'truncated' => count($rows) >= self::EXAM_LOG_LIMIT,
        ];
    }

    /**
     * Điểm + trạng thái từng cột của mỗi lượt thi. Mỗi ô: score (null nếu chưa chấm xong), max, status
     * (ac / partial / wa / pending / none), và — chỉ dùng cho người được mở bài — nội dung bài làm.
     *
     * @param  Collection<int, Attempt>  $attempts
     * @return array<int, array<string, array<string, mixed>>>  attempt id => [mã cột => ô]
     */
    private function examLogCells(Assessment $exam, Collection $attempts, bool $pdf): array
    {
        if ($attempts->isEmpty()) {
            return [];
        }

        $ids = $attempts->pluck('id')->all();
        $out = array_fill_keys($ids, []);

        $filled = function ($value) use (&$filled): bool {
            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($filled($v)) {
                        return true;
                    }
                }

                return false;
            }

            return $value !== null && trim((string) $value) !== '';
        };

        $status = function (bool $has, ?float $score, float $max, bool $accepted, bool $pending): string {
            if (! $has) {
                return 'none';
            }
            if ($pending) {
                return 'pending';
            }
            if ($accepted || ($max > 0 && $score !== null && $score >= $max - 0.005)) {
                return 'ac';
            }

            return ($score ?? 0.0) > 0 ? 'partial' : 'wa';
        };

        $cell = fn (?float $score, float $max, string $st, ?string $response, ?string $language, ?string $tests) => [
            'score' => $st === 'pending' ? null : ($st === 'none' ? 0.0 : ($score ?? 0.0)),
            'max' => $max,
            'status' => $st,
            'response' => $response,
            'language' => $language,
            'tests' => $tests,
        ];

        if (! $pdf) {
            $meta = [];
            foreach ($exam->items->sortBy('order') as $item) {
                $meta[$item->question_id] = (float) $item->effectivePoints();
            }

            AttemptAnswer::query()
                ->whereIn('attempt_id', $ids)
                ->whereIn('question_id', array_keys($meta))
                ->get()
                ->each(function (AttemptAnswer $a) use (&$out, $meta, $filled, $status, $cell) {
                    $max = $meta[$a->question_id];
                    $has = $filled($a->code_source) || $filled($a->answer);
                    $score = $a->score !== null ? (float) $a->score : null;
                    $v = $a->verdict;
                    $pending = $has && (($v instanceof VerdictStatus && ! $v->isFinal()) || ($v === null && $score === null));
                    $st = $status($has, $score, $max, $v === VerdictStatus::Accepted, $pending);

                    $response = $a->code_source !== null && trim((string) $a->code_source) !== ''
                        ? mb_substr((string) $a->code_source, 0, self::CODE_CHARS)
                        : $this->answerText($a->answer);
                    $tests = (AttemptAnswer::supportsTestCounts() && ($a->total_tests ?? 0) > 0)
                        ? (int) $a->passed_tests.'/'.(int) $a->total_tests.' test' : null;

                    $out[$a->attempt_id]['q'.$a->question_id] = $cell($score, $max, $st, $response, $a->language, $tests);
                });

            // Câu chưa có dòng trả lời nào = "Chưa làm" (để ô không bị trống).
            foreach ($ids as $id) {
                foreach ($meta as $qid => $max) {
                    $out[$id]['q'.$qid] ??= $cell(0.0, $max, 'none', null, null, null);
                }
            }

            return $out;
        }

        // Đề PDF — phiếu trả lời gộp thành MỘT cột.
        $keyMeta = AssessmentAnswerKey::query()->where('assessment_id', $exam->id)->get(['id', 'points']);
        if ($keyMeta->isNotEmpty()) {
            $keyMax = (float) $keyMeta->sum('points');
            $perAttempt = AttemptAnswerKey::query()->whereIn('attempt_id', $ids)->get()->groupBy('attempt_id');
            foreach ($ids as $id) {
                $group = $perAttempt->get($id);
                $has = $group !== null && $group->contains(fn ($g) => $filled($g->submitted_answer));
                $score = $group !== null ? (float) $group->sum(fn ($g) => (float) ($g->score ?? 0)) : 0.0;
                $correct = $group !== null ? $group->where('is_correct', true)->count() : 0;
                $out[$id]['k'] = $cell($score, $keyMax, $status($has, $score, $keyMax, false, false), null, null, $has ? $correct.'/'.$keyMeta->count().' câu đúng' : null);
            }
        }

        $codingMeta = AssessmentCodingItem::query()->where('assessment_id', $exam->id)->get()->keyBy('id');
        foreach ($codingMeta as $m) {
            foreach ($ids as $id) {
                $out[$id]['c'.$m->id] = $cell(0.0, (float) $m->points, 'none', null, null, null);
            }
        }
        if ($codingMeta->isNotEmpty()) {
            AttemptCodingItem::query()->whereIn('attempt_id', $ids)->get()->each(
                function (AttemptCodingItem $c) use (&$out, $codingMeta, $filled, $status, $cell) {
                    $m = $codingMeta->get($c->coding_item_id);
                    if ($m === null) {
                        return;
                    }
                    $max = (float) $m->points;
                    $has = $filled($c->code_source);
                    $score = $c->score !== null ? (float) $c->score : null;
                    $v = $c->verdict;
                    $pending = $has && $v instanceof VerdictStatus && ! $v->isFinal();
                    $tests = (AttemptCodingItem::supportsTestCounts() && ($c->total_tests ?? 0) > 0)
                        ? (int) $c->passed_tests.'/'.(int) $c->total_tests.' test' : null;

                    $out[$c->attempt_id]['c'.$c->coding_item_id] = $cell(
                        $score, $max, $status($has, $score, $max, $v === VerdictStatus::Accepted, $pending),
                        $has ? mb_substr((string) $c->code_source, 0, self::CODE_CHARS) : null, $c->language, $tests
                    );
                }
            );
        }

        return $out;
    }

    /** Câu trả lời dạng chữ / lựa chọn (không phải mã nguồn) thành một dòng đọc được; không có thì null. */
    private function answerText(mixed $answer): ?string
    {
        if (! is_array($answer)) {
            return $answer !== null && trim((string) $answer) !== '' ? (string) $answer : null;
        }

        if (isset($answer['text']) && trim((string) $answer['text']) !== '') {
            return (string) $answer['text'];
        }

        if (isset($answer['selected_option']) && $answer['selected_option'] !== '' && $answer['selected_option'] !== null) {
            return 'Đáp án đã chọn: '.$answer['selected_option'];
        }

        return null;
    }

    /**
     * Thứ hạng của người xem trên TOÀN đề (theo lượt đã chấm xong tốt nhất, bằng điểm đồng hạng) — chỉ
     * trả về con số, không lộ tên/điểm người khác. Null nếu người xem chưa có lượt nào chấm xong.
     */
    private function examRankOf(User $viewer, int $assessmentId): ?int
    {
        $best = Attempt::query()
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('submitted_at')
            ->whereNotNull('total_score')
            ->where(fn ($q) => $q->where('is_provisional', false)->orWhereNull('is_provisional'))
            ->where('status', '!=', AttemptStatus::Grading->value)
            ->get(['user_id', 'total_score'])
            ->groupBy('user_id')
            ->map(fn ($g) => (int) round(((float) $g->max('total_score')) * 100));

        $mine = $best->get($viewer->id);
        if ($mine === null) {
            return null;
        }

        return 1 + $best->filter(fn ($v) => $v > $mine)->count();
    }

    /** Che bớt tài khoản của người khác trên bảng điểm công khai: "ho***@gmail.com", "•••••678". */
    private function maskAccount(string $account): string
    {
        if ($account === '') {
            return '';
        }

        if (str_contains($account, '@')) {
            [$local, $domain] = explode('@', $account, 2);

            return mb_substr($local, 0, 2).'***@'.$domain;
        }

        return str_repeat('•', max(0, mb_strlen($account) - 3)).mb_substr($account, -3);
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
