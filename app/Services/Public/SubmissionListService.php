<?php

namespace App\Services\Public;

use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\PracticeAssignment;
use App\Models\Question;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * SỬA 23/10 (khách: "thêm nút Lịch sử làm bài ở tab Bài tập chuyên đề, Lịch sử làm đề ở tab Đề thi luyện tập; bấm vào ra
 * trang danh sách bài nộp như mẫu") — dữ liệu cho trang DANH SÁCH BÀI NỘP (public/practice/submissions.blade.php).
 *
 *   problems() : mỗi dòng = 1 lượt nộp bài tập chuyên đề (attempt_answers của phiên luyện, attempts.assessment_id = null).
 *   exams()    : mỗi dòng = 1 lượt nộp đề luyện tập (attempts đã nộp của đề loại Luyện tập, không tính cuộc thi).
 *
 * QUYỀN XEM — cùng luật với trang Nhật ký nộp bài (PracticeHistoryService):
 *   · admin      : mọi người;
 *   · giáo viên  : của mình + học sinh mình đã giao bài / đề (bật "Tất cả");
 *   · còn lại    : chỉ của chính mình (không có nút "Tất cả").
 * Truy vấn CHỈ chọn các cột cần thiết — KHÔNG nạp questions.grading_config (bộ test có thể rất lớn).
 */
class SubmissionListService
{
    public const PER_PAGE = 30;

    /** Bộ lọc trạng thái của 2 trang: khoá => nhãn. */
    public const PROBLEM_STATUSES = [
        'ac' => 'Đúng', 'partial' => 'Đúng một phần', 'wa' => 'Sai đáp án', 'tle' => 'Quá thời gian',
        'rte' => 'Lỗi khi chạy', 'mle' => 'Quá bộ nhớ', 'ce' => 'Dịch lỗi', 'pending' => 'Đang chấm',
    ];

    public const EXAM_STATUSES = [
        'full' => 'Điểm tối đa', 'partial' => 'Đúng một phần', 'zero' => 'Chưa đạt', 'pending' => 'Đang chấm',
    ];

    public function __construct(private PracticeAssignmentService $assignments) {}

    /** Người xem có được bật "Tất cả" (xem cả của người khác) không. */
    public function canSeeOthers(User $viewer): bool
    {
        return $this->assignments->isAdmin($viewer)
            || ($this->assignments->isTeacher($viewer) && ! $viewer->isPermissionRevoked('teacher.view-submissions'));
    }

    /**
     * id người dùng được xem; null = không giới hạn (admin bật "Tất cả").
     *
     * @return list<int>|null
     */
    private function userScope(User $viewer, bool $all): ?array
    {
        if (! $all || ! $this->canSeeOthers($viewer)) {
            return [$viewer->id];
        }

        if ($this->assignments->isAdmin($viewer)) {
            return null;
        }

        $ids = [$viewer->id];
        if ($this->assignments->isReady()) {
            $ids = array_merge($ids, PracticeAssignment::query()->where('assigned_by', $viewer->id)->distinct()->pluck('student_id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** id người dùng khớp ô tìm (tên / email / số điện thoại). */
    private function userIdsMatching(string $q): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return User::query()
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->limit(500)
            ->pluck('id')
            ->all();
    }

    // ───────────────────────── Bài tập chuyên đề ─────────────────────────

    /** @return array<string, mixed> */
    public function problems(User $viewer, array $filters): array
    {
        $all = (bool) ($filters['all'] ?? false) && $this->canSeeOthers($viewer);
        $status = array_key_exists($filters['status'] ?? '', self::PROBLEM_STATUSES) ? $filters['status'] : '';
        $q = trim((string) ($filters['q'] ?? ''));
        $scope = $this->userScope($viewer, $all);

        $base = AttemptAnswer::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->whereNull('attempts.assessment_id')
            ->whereNull('attempts.competition_id')
            ->where(fn ($w) => $w->where('attempt_answers.submission_count', '>', 0)->orWhereNotNull('attempt_answers.graded_at'))
            ->when($scope !== null, fn ($w) => $w->whereIn('attempts.user_id', $scope));

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $questionIds = Question::query()
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('code', 'like', $like))
                ->limit(500)->pluck('id')->all();
            $userIds = $all ? $this->userIdsMatching($q) : [];
            $base->where(fn ($w) => $w->whereIn('attempt_answers.question_id', $questionIds ?: [0])
                ->orWhereIn('attempts.user_id', $userIds ?: [0]));
        }

        // Thẻ số liệu đầu trang: tính theo phạm vi + ô tìm, CHƯA áp bộ lọc trạng thái.
        $total = (clone $base)->count();
        $accepted = (clone $base)->where('attempt_answers.verdict', VerdictStatus::Accepted->value)->count();

        $this->applyProblemStatus($base, $status);

        $runStats = AttemptAnswer::supportsRunStats();
        $columns = ['attempt_answers.id', 'attempt_answers.attempt_id', 'attempt_answers.question_id', 'attempt_answers.verdict',
            'attempt_answers.score', 'attempt_answers.language', 'attempt_answers.updated_at', 'attempt_answers.submission_count'];
        if (AttemptAnswer::supportsTestCounts()) {
            array_push($columns, 'attempt_answers.passed_tests', 'attempt_answers.total_tests');
        }
        if ($runStats) {
            array_push($columns, 'attempt_answers.run_time_ms', 'attempt_answers.run_memory_kb');
        }

        /** @var LengthAwarePaginator $page */
        $page = $base->select($columns)
            ->with(['attempt:id,user_id', 'attempt.user:id,name,email,phone', 'question:id,title,code,points,type'])
            ->orderByDesc('attempt_answers.updated_at')
            ->orderByDesc('attempt_answers.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rows = collect($page->items())->map(function (AttemptAnswer $a) use ($viewer, $runStats) {
            $user = $a->attempt?->user;
            $question = $a->question;
            $isCoding = ($question?->type?->value ?? null) === 'coding';
            [$key, $label] = $this->problemBadge($a, (float) ($question?->points ?? 0));

            return [
                'at' => $a->updated_at,
                'mine' => (int) $a->attempt?->user_id === (int) $viewer->id,
                'name' => $user?->name ?? 'Người dùng đã xoá',
                'account' => $user?->email ?: ($user?->phone ?: ''),
                'statusKey' => $key,
                'statusLabel' => $label,
                'title' => $question?->title ?? 'Bài đã bị xoá',
                'code' => $question?->code ?: ($question ? '#'.$question->id : ''),
                'href' => $question ? route('student.practiceByQuestion.setup', ['question' => $question->id]) : null,
                'historyHref' => $question ? route('practice.history.problem', $question->id) : null,
                'tests' => (AttemptAnswer::supportsTestCounts() && ($a->total_tests ?? 0) > 0) ? (int) $a->passed_tests.'/'.(int) $a->total_tests : null,
                'time' => $runStats && $a->run_time_ms !== null ? (int) $a->run_time_ms.'ms' : null,
                'memory' => $runStats && $a->run_memory_kb !== null ? self::memoryLabel((int) $a->run_memory_kb) : null,
                'language' => $isCoding ? self::languageLabel($a->language) : 'Trắc nghiệm / tự luận',
            ];
        })->all();

        return $this->envelope($viewer, 'problems', $page, $rows, $all, $status, $q, [
            ['label' => 'Lượt nộp', 'value' => $total, 'tone' => 'blue', 'icon' => 'send'],
            ['label' => 'Đúng', 'value' => $accepted, 'tone' => 'green', 'icon' => 'check-circle'],
            ['label' => 'Tỉ lệ đúng', 'value' => $total > 0 ? round($accepted * 100 / $total, 1).'%' : '—', 'tone' => 'amber', 'icon' => 'trending-up'],
        ]);
    }

    private function applyProblemStatus(Builder $q, string $status): void
    {
        $v = fn (VerdictStatus $s) => $s->value;
        match ($status) {
            'ac' => $q->where('attempt_answers.verdict', $v(VerdictStatus::Accepted)),
            'partial' => $q->where('attempt_answers.verdict', '!=', $v(VerdictStatus::Accepted))
                ->whereNotIn('attempt_answers.verdict', [$v(VerdictStatus::Pending), $v(VerdictStatus::Queued), $v(VerdictStatus::Judging)])
                ->where('attempt_answers.score', '>', 0),
            'wa' => $q->where('attempt_answers.verdict', $v(VerdictStatus::WrongAnswer))
                ->where(fn ($w) => $w->whereNull('attempt_answers.score')->orWhere('attempt_answers.score', '<=', 0)),
            'tle' => $q->where('attempt_answers.verdict', $v(VerdictStatus::TimeLimitExceeded)),
            'rte' => $q->where('attempt_answers.verdict', $v(VerdictStatus::RuntimeError)),
            'mle' => $q->where('attempt_answers.verdict', $v(VerdictStatus::MemoryLimitExceeded)),
            'ce' => $q->where('attempt_answers.verdict', $v(VerdictStatus::CompileError)),
            'pending' => $q->whereIn('attempt_answers.verdict', [$v(VerdictStatus::Pending), $v(VerdictStatus::Queued), $v(VerdictStatus::Judging)]),
            default => null,
        };
    }

    /** @return array{0: string, 1: string} [khoá màu, nhãn] */
    private function problemBadge(AttemptAnswer $a, float $max): array
    {
        $verdict = $a->verdict instanceof VerdictStatus ? $a->verdict : VerdictStatus::tryFrom((string) $a->verdict);
        $score = (float) ($a->score ?? 0);

        return match (true) {
            $verdict === null, in_array($verdict, [VerdictStatus::Pending, VerdictStatus::Queued, VerdictStatus::Judging], true) => ['pending', 'Đang chấm'],
            $verdict === VerdictStatus::Accepted => ['ac', 'Đúng'],
            $verdict === VerdictStatus::CompileError => ['ce', 'Dịch lỗi'],
            $verdict === VerdictStatus::SystemError => ['pending', 'Lỗi máy chấm'],
            $score > 0 => ['partial', 'Đúng một phần'],
            $verdict === VerdictStatus::TimeLimitExceeded => ['tle', 'Quá thời gian'],
            $verdict === VerdictStatus::RuntimeError => ['rte', 'Lỗi khi chạy'],
            $verdict === VerdictStatus::MemoryLimitExceeded => ['mle', 'Quá bộ nhớ'],
            default => ['wa', 'Sai đáp án'],
        };
    }

    // ───────────────────────── Đề thi luyện tập ─────────────────────────

    /** @return array<string, mixed> */
    public function exams(User $viewer, array $filters): array
    {
        $all = (bool) ($filters['all'] ?? false) && $this->canSeeOthers($viewer);
        $status = array_key_exists($filters['status'] ?? '', self::EXAM_STATUSES) ? $filters['status'] : '';
        $q = trim((string) ($filters['q'] ?? ''));
        $scope = $this->userScope($viewer, $all);

        $practiceExamIds = Assessment::query()->where('type', 'practice');

        $base = Attempt::query()
            ->whereNotNull('submitted_at')
            ->whereNull('competition_id')
            ->where('source', '!=', 'staff_test') // SỬA 30/10 — bỏ lượt LÀM THỬ của ban tổ chức (ContestTestRunController)
            ->whereIn('assessment_id', $practiceExamIds->select('id'))
            ->when($scope !== null, fn ($w) => $w->whereIn('user_id', $scope));

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $examIds = Assessment::query()
                ->where('type', 'practice')
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('exam_code', 'like', $like))
                ->limit(500)->pluck('id')->all();
            $userIds = $all ? $this->userIdsMatching($q) : [];
            $base->where(fn ($w) => $w->whereIn('assessment_id', $examIds ?: [0])->orWhereIn('user_id', $userIds ?: [0]));
        }

        $total = (clone $base)->count();
        $graded = (clone $base)->where('is_provisional', false)->whereNotNull('total_score')->count();

        $this->applyExamStatus($base, $status);

        $page = $base->select(['id', 'user_id', 'assessment_id', 'total_score', 'is_provisional', 'status', 'started_at', 'submitted_at'])
            ->with(['user:id,name,email,phone', 'assessment:id,title,exam_code,total_points,duration_minutes'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rows = collect($page->items())->map(function (Attempt $a) use ($viewer) {
            $user = $a->user;
            $exam = $a->assessment;
            $max = (float) ($exam?->total_points ?? 0);
            $score = $a->total_score !== null ? (float) $a->total_score : null;
            $pending = $a->is_provisional || $score === null;
            [$key, $label] = match (true) {
                $pending => ['pending', 'Đang chấm'],
                $max > 0 && $score >= $max - 0.005 => ['ac', 'Điểm tối đa'],
                $score > 0 => ['partial', 'Đúng một phần'],
                default => ['wa', 'Chưa đạt'],
            };
            $seconds = $a->started_at !== null ? max(0, (int) $a->submitted_at->diffInSeconds($a->started_at, true)) : null;

            return [
                'at' => $a->submitted_at,
                'mine' => (int) $a->user_id === (int) $viewer->id,
                'name' => $user?->name ?? 'Người dùng đã xoá',
                'account' => $user?->email ?: ($user?->phone ?: ''),
                'statusKey' => $key,
                'statusLabel' => $label,
                'title' => $exam?->title ?? 'Đề đã bị xoá',
                'code' => $exam?->exam_code ?: ($exam ? '#'.$exam->id : ''),
                'href' => $exam ? route('practice.exam.show', $exam->id) : null,
                'historyHref' => $exam ? route('practice.history.exam', $exam->id) : null,
                'score' => $pending ? null : self::num($score).($max > 0 ? '/'.self::num($max) : ''),
                'ratio' => (! $pending && $max > 0) ? max(0, min(100, (int) round($score * 100 / $max))) : null,
                'duration' => $seconds !== null ? self::durationLabel($seconds) : null,
            ];
        })->all();

        return $this->envelope($viewer, 'exams', $page, $rows, $all, $status, $q, [
            ['label' => 'Lượt nộp đề', 'value' => $total, 'tone' => 'blue', 'icon' => 'send'],
            ['label' => 'Đã có điểm', 'value' => $graded, 'tone' => 'green', 'icon' => 'check-circle'],
            ['label' => 'Đang chấm', 'value' => max(0, $total - $graded), 'tone' => 'amber', 'icon' => 'clock'],
        ]);
    }

    private function applyExamStatus(Builder $q, string $status): void
    {
        $graded = fn (Builder $w) => $w->where('is_provisional', false)->whereNotNull('total_score');
        match ($status) {
            'pending' => $q->where(fn ($w) => $w->where('is_provisional', true)->orWhereNull('total_score')),
            'zero' => $graded($q)->where('total_score', '<=', 0),
            'full' => $graded($q)->whereHas('assessment', fn ($e) => $e->where('total_points', '>', 0)->whereColumn('attempts.total_score', '>=', 'assessments.total_points')),
            'partial' => $graded($q)->where('total_score', '>', 0)
                ->whereHas('assessment', fn ($e) => $e->whereColumn('attempts.total_score', '<', 'assessments.total_points')),
            default => null,
        };
    }

    // ───────────────────────── Dùng chung ─────────────────────────

    private function envelope(User $viewer, string $mode, LengthAwarePaginator $page, array $rows, bool $all, string $status, string $q, array $stats): array
    {
        return [
            'mode' => $mode,
            'rows' => $rows,
            'page' => $page,
            'stats' => $stats,
            'filters' => ['all' => $all, 'status' => $status, 'q' => $q],
            'statuses' => $mode === 'problems' ? self::PROBLEM_STATUSES : self::EXAM_STATUSES,
            'canSeeOthers' => $this->canSeeOthers($viewer),
            'isAdmin' => $this->assignments->isAdmin($viewer),
        ];
    }

    public static function languageLabel(?string $language): string
    {
        $l = strtolower(str_replace([' ', '_', '-'], '', (string) $language));

        return match (true) {
            $l === '' => '--',
            (bool) preg_match('/^(cpp|c\+\+|g\+\+)14$/', $l) => 'C++ 14',
            (bool) preg_match('/^(cpp|c\+\+|g\+\+)17$/', $l), str_starts_with($l, 'cpp'), str_starts_with($l, 'c++') => 'C++',
            str_starts_with($l, 'py') => 'Python 3',
            default => (string) $language,
        };
    }

    private static function memoryLabel(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1, ',', '.'), '0'), ',').'MB' : $kb.'KB';
    }

    private static function durationLabel(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%dg %02dp', $h, $m) : ($m > 0 ? sprintf('%dp %02ds', $m, $s) : $s.'s');
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
    }
}
