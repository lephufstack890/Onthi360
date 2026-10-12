<?php

namespace App\Services\Student;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Models\AccessRight;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ClassEnrollment;
use App\Models\ClassSession;
use App\Models\ParentLink;
use App\Models\PracticeAssignment;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use App\Services\Public\PracticeAssignmentService;
use App\Services\Review\ReviewService;
use Illuminate\Support\Collection;

/**
 * SỬA 12/10 — dữ liệu cho KHU HỌC SINH dựng lại theo source mới
 * (education-main/src/components/RoleWorkspace.jsx: StudentOverview + StudentContent):
 * "Bài được giao", "Kết quả & lịch sử nộp", "Đánh giá của tôi", thẻ "Hồ sơ học tập" và
 * "Quyền & liên kết" ở Hồ sơ.
 *
 * Đây là bản MỘT HỌC SINH của Parent\ParentWorkspaceService: cùng công thức trạng thái bài
 * (completed / pending / open / overdue) và cùng nguồn dữ liệu (PracticeAssignmentService::
 * forStudent + Assignment của lớp + Attempt), chỉ khác là mỗi dòng có thêm ĐƯỜNG VÀO LÀM BÀI.
 * Chỉ đọc dữ liệu của CHÍNH học sinh đang đăng nhập.
 */
class StudentWorkspaceService
{
    public function __construct(
        private PracticeAssignmentService $practice,
        private ReviewService $reviews,
    ) {}

    // ═════════════════════════════ BÀI ĐƯỢC GIAO ═════════════════════════════

    /**
     * @return array{rows: list<array<string,mixed>>, counts: array<string,int>, next: ?array<string,mixed>, classes: list<array<string,mixed>>}
     */
    public function assignments(User $student): array
    {
        $enrollments = $this->activeEnrollments($student);
        $classIds = $enrollments->pluck('class_room_id')->unique()->values()->all();
        $rows = [];

        // (1) Giao riêng ở trang Luyện tập.
        $mine = $this->practice->forStudent($student);
        foreach (['problem', 'exam'] as $type) {
            $ids = array_keys($mine[$type] ?? []);
            if (empty($ids)) {
                continue;
            }
            $titles = $type === PracticeAssignment::TYPE_PROBLEM
                ? Question::query()->whereIn('id', $ids)->pluck('title', 'id')
                : Assessment::query()->whereIn('id', $ids)->pluck('title', 'id');

            foreach ($mine[$type] as $subjectId => $a) {
                $status = match (true) {
                    $a['status'] === 'completed' => 'completed',
                    $a['status'] === 'pending' => 'pending',
                    ! empty($a['overdue']) => 'overdue',
                    default => 'open',
                };
                $score = $a['score'] ?? null;
                $rows[] = [
                    'kind' => $type === 'exam' ? 'Đề luyện tập' : 'Bài luyện tập',
                    'title' => $titles->get($subjectId) ?? 'Nội dung đã bị xoá',
                    'by' => $a['teacher'] ?? null,
                    'assignedAt' => $a['assignedAt'] ?? null,
                    'deadline' => $a['deadline'] ?? null,
                    'deadlineTs' => $a['deadlineTs'] ?? null,
                    'status' => $status,
                    'late' => (bool) ($a['late'] ?? false),
                    'result' => $score !== null && ($a['maxScore'] ?? null) ? $this->num((float) $score).'/'.$this->num((float) $a['maxScore']) : ($a['resultLabel'] ?? null),
                    'submittedAt' => $a['submittedAt'] ?? null,
                    'url' => $titles->has($subjectId)
                        ? ($type === 'exam'
                            ? route('student.assessment.take', $subjectId)
                            : route('student.practiceByQuestion.setup', ['question' => $subjectId]))
                        : null,
                ];
            }
        }

        // (2) Bài của lớp (chỉ bài đã mở / đã đóng, không hiện bản nháp).
        if (! empty($classIds)) {
            $classAssignments = Assignment::query()
                ->whereIn('class_room_id', $classIds)
                ->whereIn('status', ['open', 'closed'])
                ->with(['assessment:id,title', 'classRoom:id,name'])
                ->orderByDesc('due_at')
                ->limit(150)
                ->get();

            $attemptMap = $classAssignments->isEmpty() ? collect() : Attempt::query()
                ->where('user_id', $student->id)
                ->whereIn('assignment_id', $classAssignments->pluck('id'))
                ->orderByDesc('id')
                ->get()
                ->groupBy('assignment_id');

            foreach ($classAssignments as $a) {
                $attempt = ($attemptMap->get($a->id) ?? collect())->first(fn ($x) => $x->submitted_at !== null);
                $due = $a->due_at ?? $a->closes_at;
                $status = match (true) {
                    $attempt !== null && $attempt->total_score === null => 'pending',
                    $attempt !== null => 'completed',
                    $due !== null && $due->isPast() => 'overdue',
                    default => 'open',
                };
                $rows[] = [
                    'kind' => 'Bài của lớp',
                    'title' => $a->assessment->title ?? 'Bài tập',
                    'by' => $a->classRoom->name ?? null,
                    'assignedAt' => $a->opens_at?->format('d/m/Y'),
                    'deadline' => $due?->format('d/m/Y · H:i'),
                    'deadlineTs' => $due?->getTimestamp(),
                    'status' => $status,
                    'late' => $attempt !== null && $due !== null && $attempt->submitted_at?->gt($due),
                    'result' => $attempt?->total_score !== null ? $this->num((float) $attempt->total_score) : null,
                    'submittedAt' => $attempt?->submitted_at?->format('H:i d/m/Y'),
                    'url' => $attempt
                        ? route('student.assessment.result', $attempt->id)
                        : ($a->assessment_id ? route('student.assessment.take', ['assessment' => $a->assessment_id, 'assignment' => $a->id]) : null),
                ];
            }
        }

        usort($rows, function ($x, $y) {
            $rank = ['overdue' => 0, 'open' => 1, 'pending' => 2, 'completed' => 3];
            if ($rank[$x['status']] !== $rank[$y['status']]) {
                return $rank[$x['status']] <=> $rank[$y['status']];
            }

            return ($x['deadlineTs'] ?? PHP_INT_MAX) <=> ($y['deadlineTs'] ?? PHP_INT_MAX);
        });

        $counts = ['total' => count($rows), 'completed' => 0, 'pending' => 0, 'open' => 0, 'overdue' => 0];
        $next = null;
        foreach ($rows as $r) {
            $counts[$r['status']]++;
            if ($r['status'] === 'open' && ($next === null || ($r['deadlineTs'] ?? PHP_INT_MAX) < ($next['deadlineTs'] ?? PHP_INT_MAX))) {
                $next = $r;
            }
        }

        return ['rows' => array_slice($rows, 0, 200), 'counts' => $counts, 'next' => $next];
    }

    // ═════════════════════════════ KẾT QUẢ ═════════════════════════════

    /** @return array<string,mixed> */
    public function results(User $student): array
    {
        $attempts = Attempt::query()
            ->where('user_id', $student->id)
            ->whereNotNull('submitted_at')
            ->with('assessment:id,title')
            ->latest('submitted_at')
            ->limit(150)
            ->get();

        $rows = $attempts->map(fn (Attempt $a) => [
            'title' => $a->assessment->title ?? 'Bài đã nộp',
            'score' => $a->total_score !== null ? $this->num((float) $a->total_score) : null,
            'pending' => $a->total_score === null,
            'provisional' => (bool) $a->is_provisional,
            'date' => $a->submitted_at?->format('d/m/Y H:i'),
            'ago' => $a->submitted_at?->diffForHumans(),
            'url' => route('student.assessment.result', $a->id),
        ])->values()->all();

        $scored = $attempts->filter(fn ($a) => $a->total_score !== null)->values();
        $latest = $scored->first();
        $previous = $latest ? $scored->first(fn ($a) => $a->id !== $latest->id) : null;
        $best = $scored->sortByDesc('total_score')->first();

        // Tiến độ lớp: cùng công thức "% buổi đã kết thúc / tổng buổi" của Student\DashboardService.
        $enrollments = $this->activeEnrollments($student);
        $classIds = $enrollments->pluck('class_room_id')->unique()->values()->all();
        $progressRows = $classIds
            ? app(ClassSessionRepositoryInterface::class)->sessionProgressCountsForClassRoomIds($classIds)->keyBy('class_room_id')
            : collect();
        $classProgress = [];
        $ended = 0;
        $total = 0;
        foreach ($enrollments as $e) {
            $row = $progressRows->get($e->class_room_id);
            $t = (int) ($row->total ?? 0);
            $en = min((int) ($row->ended ?? 0), $t);
            $classProgress[] = [
                'class' => self::classLabel($e->classRoom->name ?? null),
                'course' => $e->classRoom->course->title ?? null,
                'percent' => $t > 0 ? (int) round($en / $t * 100) : 0,
                'ended' => $en,
                'total' => $t,
            ];
            $ended += $en;
            $total += $t;
        }

        return [
            'rows' => $rows,
            'count' => count($rows),
            'latest' => $latest ? [
                'title' => $latest->assessment->title ?? 'Bài đã nộp',
                'score' => $this->num((float) $latest->total_score),
                'delta' => $previous ? round((float) $latest->total_score - (float) $previous->total_score, 2) : null,
                'ago' => $latest->submitted_at?->diffForHumans(),
            ] : null,
            'average' => $scored->isNotEmpty() ? round((float) $scored->avg('total_score'), 1) : null,
            'best' => $best ? ['score' => $this->num((float) $best->total_score), 'title' => $best->assessment->title ?? 'Bài đã nộp'] : null,
            'pendingCount' => $attempts->whereNull('total_score')->count(),
            'classProgress' => $classProgress,
            'progressPercent' => $total > 0 ? (int) round($ended / $total * 100) : 0,
            'ended' => $ended,
            'sessions' => $total,
        ];
    }

    // ═════════════════════════════ ĐÁNH GIÁ ═════════════════════════════

    /** @return array{reviewable: list<array<string,mixed>>, mine: list<array<string,mixed>>} */
    public function reviews(User $student): array
    {
        $reviewable = [];
        foreach ($this->activeEnrollments($student) as $e) {
            if (! $e->classRoom) {
                continue;
            }
            $classId = (int) $e->class_room_id;
            $decision = $this->reviews->checkEligibility($student, 'class', $classId);
            $existing = $this->reviews->findExistingReview($student, 'class', $classId);
            $reviewable[] = [
                'class' => self::classLabel($e->classRoom->name),
                'course' => $e->classRoom->course->title ?? null,
                'classId' => $classId,
                'allowed' => (bool) $decision->allowed,
                'message' => $decision->allowed ? null : $decision->message,
                'existing' => $existing ? [
                    'rating' => (int) round($existing->overall_rating),
                    'status' => $existing->status->value,
                    'editable' => $existing->isEditable(),
                    'editableUntil' => $existing->editable_until?->format('d/m/Y'),
                    'comment' => $existing->comment,
                ] : null,
            ];
        }

        $mine = $this->reviews->buildMyReviews($student)['myReviews'] ?? [];
        foreach ($mine as &$r) {
            $r['url'] = route('reviews.form', ['type' => $r['type'], 'id' => $r['targetId']]);
        }
        unset($r);

        return ['reviewable' => $reviewable, 'mine' => array_values($mine)];
    }

    // ═════════════════════════════ HỒ SƠ ═════════════════════════════

    /** Thẻ "Hồ sơ học tập" + "Quyền & liên kết" ở trang Hồ sơ. @return array<string,mixed> */
    public function profileSummary(User $student): array
    {
        $enrollment = $this->activeEnrollments($student)->first();
        $classRoom = $enrollment?->classRoom;

        $fields = [$student->name, $student->phone ?? null, $student->province ?? null, $student->avatar_path ?? null];
        $filled = count(array_filter($fields, fn ($v) => filled($v)));

        $rights = AccessRight::query()
            ->where('user_id', $student->id)
            ->whereIn('scope', [AccessScope::PersonalLearning->value, AccessScope::TeacherTeaching->value])
            ->where('status', AccessRightStatus::Active)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
        $productIds = $rights->pluck('product_id')->filter()->unique();
        $perpetual = $rights->contains(fn ($r) => $r->expires_at === null);
        $latestExpiry = $rights->whereNotNull('expires_at')->max('expires_at');
        $soonest = $rights->whereNotNull('expires_at')->min('expires_at');

        $links = ParentLink::query()->where('student_user_id', $student->id)->with('parent:id,name')->get();
        $verified = $links->filter(fn (ParentLink $l) => $l->isVerified());

        return [
            'class' => $classRoom ? self::classLabel($classRoom->name) : 'Chưa có lớp',
            'course' => $classRoom->course->title ?? null,
            'completeness' => (int) round($filled / count($fields) * 100),
            'access' => [
                'count' => $productIds->count(),
                'until' => $perpetual ? 'không giới hạn' : ($latestExpiry ? $latestExpiry->format('d/m/Y') : null),
                'expiringSoon' => $soonest !== null && $soonest->lte(now()->addDays(14)),
                'soonest' => $soonest?->format('d/m/Y'),
            ],
            'parents' => [
                'total' => $links->count(),
                'verified' => $verified->count(),
                'first' => $verified->first()?->parent?->name ?? $links->first()?->parent?->name,
            ],
        ];
    }

    // ═════════════════════════════ chung ═════════════════════════════

    /** @return Collection<int, ClassEnrollment> */
    private function activeEnrollments(User $student): Collection
    {
        return ClassEnrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->with('classRoom.course')
            ->get();
    }

    public static function classLabel(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'Chưa có lớp';
        }

        return preg_match('/^lớp\s/iu', $name) ? $name : 'Lớp '.$name;
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
    }
}
