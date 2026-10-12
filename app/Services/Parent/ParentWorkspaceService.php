<?php

namespace App\Services\Parent;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Attendance;
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
 * SỬA 12/10 — dữ liệu cho KHU PHỤ HUYNH dựng lại theo source mới
 * (education-main/src/components/RoleWorkspace.jsx: ParentOverview + ParentContent).
 *
 * Mọi mục (Tổng quan, Con của tôi, Bài được giao, Lịch & Điểm danh, Kết quả & Tiến độ,
 * Đánh giá lớp) dùng CHUNG một "ảnh chụp" theo con đang theo dõi, như ô chọn "Đối tượng theo
 * dõi" của bản mẫu. Quyền xem vẫn đúng luật cũ (10.3): CHỈ con có liên kết ĐÃ XÁC MINH.
 * Không có con nào thì mọi mục trả khung rỗng, view tự hiện hướng dẫn liên kết.
 *
 * Các truy vấn được gộp theo lô (whereIn) để không bị N+1 khi phụ huynh có nhiều con.
 */
class ParentWorkspaceService
{
    public function __construct(
        private PracticeAssignmentService $practice,
        private ReviewService $reviews,
    ) {}

    /**
     * @param  string|null  $childParam  'all' | id con | null (mặc định: con đầu tiên, hoặc 'all' nếu có nhiều con)
     * @return array<string, mixed>
     */
    public function snapshot(User $parent, ?string $childParam = null): array
    {
        $links = ParentLink::query()
            ->where('parent_user_id', $parent->id)
            ->where('status', 'verified')
            ->with('student')
            ->get()
            ->filter(fn (ParentLink $l) => $l->student !== null)
            ->values();

        $students = $links->map(fn (ParentLink $l) => $l->student)->unique('id')->values();
        $studentIds = array_map('intval', $students->pluck('id')->all());

        $enrollments = $this->activeEnrollments($studentIds);
        $classRoomIds = $enrollments->pluck('class_room_id')->unique()->values()->all();

        $children = $students->map(function (User $s) use ($enrollments) {
            $classRoom = $enrollments->get($s->id)?->classRoom;

            return [
                'id' => (int) $s->id,
                'name' => $s->name,
                'class' => self::classLabel($classRoom->name ?? null),
                'classId' => isset($classRoom->id) ? (int) $classRoom->id : null,
                'course' => $classRoom->course->title ?? null,
                'avatar' => $s->avatarUrl(),
                'initial' => mb_strtoupper(mb_substr($s->name, 0, 1)),
            ];
        })->values()->all();

        // ── Chọn đối tượng theo dõi ──────────────────────────────────────────
        $selected = 'all';
        if (count($children) === 1) {
            $selected = $children[0]['id'];
        } elseif ($childParam !== null && $childParam !== 'all' && in_array((int) $childParam, $studentIds, true)) {
            $selected = (int) $childParam;
        }
        $viewIds = $selected === 'all' ? $studentIds : [$selected];
        $viewChildren = array_values(array_filter($children, fn ($c) => in_array($c['id'], $viewIds, true)));
        $viewClassIds = array_values(array_unique(array_filter(array_map(fn ($c) => $c['classId'], $viewChildren))));

        $base = [
            'children' => $children,
            'hasChildren' => count($children) > 0,
            'selected' => $selected,
            'viewChildren' => $viewChildren,
            'multi' => count($children) > 1,
        ];

        if (empty($viewIds)) {
            return $base + $this->emptyMetrics();
        }

        $names = collect($children)->pluck('name', 'id');
        $classOf = collect($children)->pluck('class', 'id');

        // ── Tiến độ lớp + điểm danh ──────────────────────────────────────────
        // Cùng công thức "% buổi đã kết thúc / tổng buổi" như Parent\DashboardService (qua repository).
        $progressRows = $viewClassIds
            ? app(ClassSessionRepositoryInterface::class)->sessionProgressCountsForClassRoomIds($viewClassIds)->keyBy('class_room_id')
            : collect();

        $pastRows = $viewClassIds
            ? ClassSession::query()->whereIn('class_room_id', $viewClassIds)->where('starts_at', '<', now())
                ->selectRaw('class_room_id, count(*) as cnt')->groupBy('class_room_id')->pluck('cnt', 'class_room_id')
            : collect();

        $attendanceRows = $viewClassIds
            ? Attendance::query()
                ->whereIn('student_id', $viewIds)
                ->whereHas('classSession', fn ($q) => $q->whereIn('class_room_id', $viewClassIds))
                ->with('classSession:id,class_room_id,starts_at,topic')
                ->get()
            : collect();

        $classProgress = [];
        $presentTotal = 0;
        $pastTotal = 0;
        $endedTotal = 0;
        $sessionTotal = 0;
        foreach ($viewChildren as $c) {
            if (! $c['classId']) {
                continue;
            }
            $row = $progressRows->get($c['classId']);
            $total = (int) ($row->total ?? 0);
            $ended = (int) ($row->ended ?? 0);
            $past = (int) ($pastRows->get($c['classId']) ?? 0);
            $present = $attendanceRows->where('student_id', $c['id'])
                ->filter(fn ($a) => in_array($a->status->value, ['present', 'late'], true))->count();

            $classProgress[] = [
                'childId' => $c['id'],
                'child' => $c['name'],
                'class' => $c['class'],
                'percent' => $total > 0 ? (int) round(min($ended, $total) / $total * 100) : 0,
                'ended' => $ended,
                'total' => $total,
                'present' => $present,
                'past' => $past,
            ];
            $presentTotal += $present;
            $pastTotal += $past;
            $endedTotal += min($ended, $total);
            $sessionTotal += $total;
        }
        $progressPercent = $sessionTotal > 0 ? (int) round($endedTotal / $sessionTotal * 100) : 0;
        $attendancePercent = $pastTotal > 0 ? (int) round($presentTotal / $pastTotal * 100) : null;

        // ── Lịch học sắp tới + điểm danh gần đây ─────────────────────────────
        $upcoming = $viewClassIds
            ? ClassSession::query()->whereIn('class_room_id', $viewClassIds)->where('starts_at', '>=', now())
                ->with('classRoom:id,name')->orderBy('starts_at')->limit(24)->get()
            : collect();
        $classToChild = collect($viewChildren)->pluck('name', 'classId');

        $upcomingRows = $upcoming->map(fn (ClassSession $s) => [
            'when' => $s->starts_at->isToday() ? 'Hôm nay' : $s->starts_at->format('d/m'),
            'weekday' => $this->weekday($s->starts_at),
            'time' => $s->starts_at->format('H:i').($s->ends_at ? ' – '.$s->ends_at->format('H:i') : ''),
            'start' => $s->starts_at->format('H:i'),
            'title' => $s->topic ?: ($s->classRoom->name ?? 'Buổi học'),
            'class' => self::classLabel($s->classRoom->name ?? null),
            'child' => $classToChild->get($s->class_room_id),
            'today' => $s->starts_at->isToday(),
        ])->all();

        $attendanceTone = fn (string $v) => match ($v) {
            'present' => 'emerald', 'late' => 'blue', 'excused' => 'amber', default => 'rose',
        };
        $attendanceLabel = fn (string $v) => match ($v) {
            'present' => 'Có mặt', 'late' => 'Đi muộn', 'excused' => 'Vắng có phép', 'absent' => 'Vắng không phép', default => $v,
        };
        $attendanceList = $attendanceRows
            ->filter(fn ($a) => $a->classSession?->starts_at !== null)
            ->sortByDesc(fn ($a) => $a->classSession->starts_at->getTimestamp())
            ->take(60)
            ->map(fn ($a) => [
                'child' => $names->get($a->student_id),
                'date' => $a->classSession->starts_at->format('d/m/Y'),
                'weekday' => $this->weekday($a->classSession->starts_at),
                'topic' => $a->classSession->topic,
                'status' => $attendanceLabel($a->status->value),
                'tone' => $attendanceTone($a->status->value),
                'note' => $a->note,
                'ts' => $a->classSession->starts_at->getTimestamp(),
            ])->values()->all();

        // ── Kết quả ──────────────────────────────────────────────────────────
        $attempts = Attempt::query()
            ->whereIn('user_id', $viewIds)
            ->whereNotNull('submitted_at')
            ->with('assessment:id,title')
            ->latest('submitted_at')
            ->limit(100)
            ->get();

        $results = $attempts->take(60)->map(fn (Attempt $a) => [
            'childId' => $a->user_id,
            'child' => $names->get($a->user_id),
            'title' => $a->assessment->title ?? 'Bài đã nộp',
            'score' => $a->total_score !== null ? $this->num((float) $a->total_score) : null,
            'pending' => $a->total_score === null,
            'provisional' => (bool) $a->is_provisional,
            'time' => $a->submitted_at?->diffForHumans(),
            'date' => $a->submitted_at?->format('d/m/Y H:i'),
            'ts' => $a->submitted_at?->getTimestamp() ?? 0,
        ])->values()->all();

        $scored = $attempts->filter(fn ($a) => $a->total_score !== null)->values();
        $latest = $scored->first();
        $previous = $latest ? $scored->first(fn ($a) => $a->user_id === $latest->user_id && $a->id !== $latest->id) : null;
        $latestResult = $latest ? [
            'child' => $names->get($latest->user_id),
            'title' => $latest->assessment->title ?? 'Bài đã nộp',
            'score' => $this->num((float) $latest->total_score),
            'delta' => $previous ? round((float) $latest->total_score - (float) $previous->total_score, 2) : null,
            'time' => $latest->submitted_at?->diffForHumans(),
        ] : null;
        $average = $scored->isNotEmpty() ? round((float) $scored->avg('total_score'), 1) : null;

        // ── Hoạt động gần đây (bài đã nộp + buổi học đã điểm danh) ───────────
        $activity = [];
        foreach ($attempts->take(6) as $a) {
            $activity[] = ['status' => 'Đã nộp', 'title' => $a->assessment->title ?? 'Bài đã nộp', 'child' => $names->get($a->user_id),
                'time' => $a->submitted_at?->format('H:i d/m'), 'ts' => $a->submitted_at?->getTimestamp() ?? 0, 'tone' => 'blue'];
        }
        foreach (array_slice($attendanceList, 0, 6) as $row) {
            $activity[] = ['status' => $row['status'], 'title' => $row['topic'] ?: 'Buổi học', 'child' => $row['child'],
                'time' => $row['date'], 'ts' => $row['ts'], 'tone' => $row['tone']];
        }
        usort($activity, fn ($x, $y) => $y['ts'] <=> $x['ts']);
        $activity = array_slice($activity, 0, 6);

        // ── Bài được giao ────────────────────────────────────────────────────
        $assignments = $this->assignmentsFor($viewChildren, $viewClassIds);

        // ── Đánh giá lớp ─────────────────────────────────────────────────────
        $reviewable = [];
        foreach ($viewChildren as $c) {
            if (! $c['classId']) {
                continue;
            }
            $decision = $this->reviews->checkEligibility($parent, 'class', (int) $c['classId']);
            $existing = $this->reviews->findExistingReview($parent, 'class', (int) $c['classId']);
            $reviewable[] = [
                'child' => $c['name'],
                'class' => $c['class'],
                'classId' => $c['classId'],
                'allowed' => (bool) $decision->allowed,
                'message' => $decision->allowed ? null : $decision->message,
                'existing' => $existing ? [
                    'rating' => (int) $existing->overall_rating,
                    'status' => $existing->status->value,
                    'editable' => $existing->isEditable(),
                    'editableUntil' => $existing->editable_until?->format('d/m/Y'),
                    'comment' => $existing->comment,
                ] : null,
            ];
        }

        return $base + [
            'progressPercent' => $progressPercent,
            'endedSessions' => $endedTotal,
            'totalSessions' => $sessionTotal,
            'attendancePercent' => $attendancePercent,
            'presentSessions' => $presentTotal,
            'pastSessions' => $pastTotal,
            'classProgress' => $classProgress,
            'upcoming' => $upcomingRows,
            'attendanceList' => $attendanceList,
            'results' => $results,
            'latestResult' => $latestResult,
            'averageScore' => $average,
            'submittedCount' => $attempts->count(),
            'activity' => $activity,
            'assignments' => $assignments['rows'],
            'assignmentCounts' => $assignments['counts'],
            'nextAssignment' => $assignments['next'],
            'reviewable' => $reviewable,
            'tip' => $this->tip($assignments['next'], $upcomingRows[0] ?? null, $latestResult, $viewChildren),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════

    /** @return array<string, mixed> khung rỗng khi chưa có con nào được xác minh */
    private function emptyMetrics(): array
    {
        return [
            'progressPercent' => 0, 'endedSessions' => 0, 'totalSessions' => 0,
            'attendancePercent' => null, 'presentSessions' => 0, 'pastSessions' => 0,
            'classProgress' => [], 'upcoming' => [], 'attendanceList' => [], 'results' => [],
            'latestResult' => null, 'averageScore' => null, 'submittedCount' => 0, 'activity' => [],
            'assignments' => [], 'assignmentCounts' => ['total' => 0, 'completed' => 0, 'pending' => 0, 'open' => 0, 'overdue' => 0],
            'nextAssignment' => null, 'reviewable' => [], 'tip' => null,
        ];
    }

    /** @return Collection<int, ClassEnrollment> khoá = student_id */
    private function activeEnrollments(array $studentIds): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        return ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->with('classRoom.course')
            ->get()
            ->keyBy('student_id');
    }

    /**
     * Bài được giao cho các con: (1) bài/đề giao riêng ở trang Luyện tập, (2) bài của lớp.
     * Trạng thái dùng chung 4 giá trị: completed / pending (chờ chấm) / open (chưa nộp, còn hạn)
     * / overdue (quá hạn chưa nộp).
     *
     * @return array{rows: list<array<string, mixed>>, counts: array<string,int>, next: ?array<string,mixed>}
     */
    private function assignmentsFor(array $viewChildren, array $classRoomIds): array
    {
        $rows = [];

        // (1) Giao riêng — dùng đúng bộ tính trạng thái của học sinh (PracticeAssignmentService::forStudent).
        foreach ($viewChildren as $c) {
            $student = User::query()->find($c['id']);
            if (! $student) {
                continue;
            }
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
                        'child' => $c['name'],
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
                    ];
                }
            }
        }

        // (2) Bài của lớp (Assignment) — chỉ bài đã mở / đã đóng, không hiện bản nháp.
        if (! empty($classRoomIds)) {
            $classAssignments = Assignment::query()
                ->whereIn('class_room_id', $classRoomIds)
                ->whereIn('status', ['open', 'closed'])
                ->with(['assessment:id,title', 'classRoom:id,name'])
                ->orderByDesc('due_at')
                ->limit(150)
                ->get();

            $studentIds = array_column($viewChildren, 'id');
            $attemptMap = $classAssignments->isEmpty() ? collect() : Attempt::query()
                ->whereIn('user_id', $studentIds)
                ->whereIn('assignment_id', $classAssignments->pluck('id'))
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn ($a) => $a->user_id.'-'.$a->assignment_id);

            foreach ($classAssignments as $a) {
                foreach ($viewChildren as $c) {
                    if ((int) $c['classId'] !== (int) $a->class_room_id) {
                        continue;
                    }
                    $attempt = ($attemptMap->get($c['id'].'-'.$a->id) ?? collect())->first(fn ($x) => $x->submitted_at !== null);
                    $due = $a->due_at ?? $a->closes_at;
                    $status = match (true) {
                        $attempt !== null && $attempt->total_score === null => 'pending',
                        $attempt !== null => 'completed',
                        $due !== null && $due->isPast() => 'overdue',
                        default => 'open',
                    };
                    $rows[] = [
                        'child' => $c['name'],
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
                    ];
                }
            }
        }

        // Chưa xong lên trước (sắp hết hạn trước), sau đó đến đã xong (mới nhất trước).
        usort($rows, function ($x, $y) {
            $rank = ['overdue' => 0, 'open' => 1, 'pending' => 2, 'completed' => 3];
            if ($rank[$x['status']] !== $rank[$y['status']]) {
                return $rank[$x['status']] <=> $rank[$y['status']];
            }

            return ($x['deadlineTs'] ?? PHP_INT_MAX) <=> ($y['deadlineTs'] ?? PHP_INT_MAX);
        });

        $counts = ['total' => count($rows), 'completed' => 0, 'pending' => 0, 'open' => 0, 'overdue' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']]++;
        }

        $next = null;
        foreach ($rows as $r) {
            if ($r['status'] === 'open') {
                if ($next === null || ($r['deadlineTs'] ?? PHP_INT_MAX) < ($next['deadlineTs'] ?? PHP_INT_MAX)) {
                    $next = $r;
                }
            }
        }

        return ['rows' => array_slice($rows, 0, 200), 'counts' => $counts, 'next' => $next];
    }

    /** "Gợi ý đồng hành hôm nay" — suy ra từ dữ liệu thật, ưu tiên bài sắp hạn, rồi buổi học tới, rồi kết quả mới. */
    private function tip(?array $nextAssignment, ?array $nextSession, ?array $latestResult, array $viewChildren): ?array
    {
        $who = count($viewChildren) === 1 ? $viewChildren[0]['name'] : 'Các con';

        if ($nextAssignment) {
            return [
                'title' => 'Hoàn tất bài '.$nextAssignment['title'],
                'text' => ($nextAssignment['child'] ? $nextAssignment['child'].' còn ' : 'Còn ')
                    .'1 bài chưa nộp'.($nextAssignment['deadline'] ? ', hạn '.$nextAssignment['deadline'] : '').'. Bạn có thể nhắc con hoàn tất đúng hạn.',
                'cta' => 'Xem bài được giao', 'route' => 'parent.assignments.index',
            ];
        }
        if ($nextSession) {
            return [
                'title' => 'Chuẩn bị cho buổi học: '.$nextSession['title'],
                'text' => ($nextSession['child'] ?: $who).' có buổi học '.mb_strtolower($nextSession['when']).' lúc '.$nextSession['start'].'. Nhắc con chuẩn bị bài và vào lớp đúng giờ.',
                'cta' => 'Xem lịch học', 'route' => 'parent.schedule.index',
            ];
        }
        if ($latestResult) {
            return [
                'title' => 'Xem kết quả mới nhất',
                'text' => $latestResult['child'].' vừa đạt '.$latestResult['score'].' điểm ở "'.$latestResult['title'].'". Cùng trao đổi với con về phần làm tốt và phần cần cải thiện.',
                'cta' => 'Xem kết quả', 'route' => 'parent.results.index',
            ];
        }

        return [
            'title' => 'Chưa có việc cần nhắc hôm nay',
            'text' => 'Khi giáo viên giao bài hoặc lên lịch buổi học mới, gợi ý đồng hành sẽ xuất hiện ở đây.',
            'cta' => null, 'route' => null,
        ];
    }

    /** "HSG Tin 10A1" -> "Lớp HSG Tin 10A1"; tên đã có chữ "Lớp" thì giữ nguyên; rỗng -> "Chưa có lớp". */
    public static function classLabel(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'Chưa có lớp';
        }

        return preg_match('/^lớp\s/iu', $name) ? $name : 'Lớp '.$name;
    }

    private function weekday(\DateTimeInterface $d): string
    {
        return ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'][(int) $d->format('w')];
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
