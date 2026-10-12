<?php

namespace App\Services\Student;

use App\Enums\AccessRightStatus;
use App\Enums\AccessScope;
use App\Models\AccessRight;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Enums\VerdictStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\ClassEnrollment;
use App\Models\ClassRoom;
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
     * Mỗi dòng theo mô hình của StudentAssignmentInbox.jsx (source mới): kind exercise|exam,
     * state new|active|submitted|completed, kèm lớp/giáo viên, hạn nộp, bài nộp, kết quả, mô tả.
     *
     * @return array{rows: list<array<string,mixed>>, counts: array<string,int>, pending: list<array<string,mixed>>}
     */
    public function assignments(User $student): array
    {
        $enrollments = $this->activeEnrollments($student);
        $classIds = $enrollments->pluck('class_room_id')->unique()->values()->all();
        $now = now()->getTimestamp();
        $rows = [];

        // (1) Giao riêng ở trang Luyện tập.
        $mine = $this->practice->forStudent($student);
        $examIds = array_keys($mine['exam'] ?? []);
        $examInfo = empty($examIds) ? collect() : Assessment::query()->whereIn('id', $examIds)->withCount('items')->get()->keyBy('id');
        $startedExams = empty($examIds) ? [] : Attempt::query()->where('user_id', $student->id)->whereIn('assessment_id', $examIds)
            ->whereNull('submitted_at')->pluck('assessment_id')->flip()->all();

        foreach (['problem', 'exam'] as $type) {
            $ids = array_keys($mine[$type] ?? []);
            if (empty($ids)) {
                continue;
            }
            $titles = $type === PracticeAssignment::TYPE_PROBLEM
                ? Question::query()->whereIn('id', $ids)->pluck('title', 'id')
                : $examInfo->pluck('title', 'id');

            foreach ($mine[$type] as $subjectId => $a) {
                $exam = $type === 'exam';
                $state = match (true) {
                    $a['status'] === 'completed' => 'completed',
                    $a['status'] === 'pending' => 'submitted',
                    $exam && isset($startedExams[$subjectId]) => 'active',
                    default => 'new',
                };
                $score = $a['score'] ?? null;
                $max = $a['maxScore'] ?? null;
                $info = $exam ? $examInfo->get($subjectId) : null;
                $rows[] = [
                    'id' => 'p'.($a['id'] ?? $subjectId).'-'.$type,
                    'kind' => $exam ? 'exam' : 'exercise',
                    'title' => $titles->get($subjectId) ?? 'Nội dung đã bị xoá',
                    'className' => 'Giao riêng',
                    'classId' => null,
                    'teacher' => $a['teacher'] ?? 'Giáo viên',
                    'meta' => $exam
                        ? (($info?->items_count ?? 0) > 0 ? $info->items_count.' câu' : 'Đề luyện tập').($info?->duration_minutes ? ' · '.$info->duration_minutes.' phút' : '')
                        : '1 bài lập trình',
                    'assignedAt' => $a['assignedAt'] ?? null,
                    'deadline' => $a['deadline'] ?? null,
                    'deadlineTs' => $a['deadlineTs'] ?? null,
                    'overdue' => in_array($state, ['new', 'active'], true) && ! empty($a['deadlineTs']) && $a['deadlineTs'] < $now,
                    'state' => $state,
                    'late' => (bool) ($a['late'] ?? false),
                    'result' => $state === 'completed'
                        ? ($score !== null && $max ? $this->num((float) $score).'/'.$this->num((float) $max) : ($a['resultLabel'] ?? null))
                        : null,
                    'verdict' => $state === 'completed' ? ($a['resultLabel'] ?? null) : null,
                    'submittedAt' => $a['submittedAt'] ?? null,
                    'feedback' => $a['feedback'] ?? null,
                    'description' => $exam
                        ? 'Đề luyện tập do giáo viên giao riêng cho bạn. Mở đề để làm bài và nộp trước hạn.'
                        : 'Bài lập trình do giáo viên giao riêng cho bạn. Mở bài để viết lời giải và nộp trước hạn.',
                    'url' => $titles->has($subjectId)
                        ? ($exam
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
                ->with(['assessment' => fn ($q) => $q->withCount('items'), 'classRoom.teachers:id,name'])
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
                $list = $attemptMap->get($a->id) ?? collect();
                $attempt = $list->first(fn ($x) => $x->submitted_at !== null);
                $draft = $attempt === null ? $list->first() : null;
                $due = $a->due_at ?? $a->closes_at;
                $state = match (true) {
                    $attempt !== null && $attempt->total_score === null => 'submitted',
                    $attempt !== null => 'completed',
                    $draft !== null => 'active',
                    default => 'new',
                };
                $info = $a->assessment;
                $rows[] = [
                    'id' => 'c'.$a->id,
                    'kind' => 'exam',
                    'title' => $info->title ?? 'Bài tập',
                    'className' => self::classLabel($a->classRoom->name ?? null),
                    'classId' => (int) $a->class_room_id,
                    'teacher' => $a->classRoom?->teachers->first()?->name ?? 'Giáo viên lớp',
                    'meta' => (($info?->items_count ?? 0) > 0 ? $info->items_count.' câu' : 'Bài của lớp').($info?->duration_minutes ? ' · '.$info->duration_minutes.' phút' : ''),
                    'assignedAt' => $a->opens_at?->format('d/m/Y H:i'),
                    'deadline' => $due?->format('d/m/Y · H:i'),
                    'deadlineTs' => $due?->getTimestamp(),
                    'overdue' => in_array($state, ['new', 'active'], true) && $due !== null && $due->isPast(),
                    'state' => $state,
                    'late' => $attempt !== null && $due !== null && (bool) $attempt->submitted_at?->gt($due),
                    'result' => $attempt?->total_score !== null ? $this->num((float) $attempt->total_score) : null,
                    'verdict' => null,
                    'submittedAt' => $attempt?->submitted_at?->format('d/m/Y H:i'),
                    'description' => 'Bài do giáo viên giao cho cả lớp. Mở bài để làm và nộp trước hạn; điểm hiện khi giáo viên chấm xong.',
                    'url' => $attempt
                        ? route('student.assessment.result', $attempt->id)
                        : ($a->assessment_id ? route('student.assessment.take', ['assessment' => $a->assessment_id, 'assignment' => $a->id]) : null),
                ];
            }
        }

        // Cần làm trước (hạn gần nhất), sau đó chờ chấm, cuối cùng đã hoàn thành.
        usort($rows, function ($x, $y) {
            $rank = ['new' => 0, 'active' => 0, 'submitted' => 1, 'completed' => 2];
            if ($rank[$x['state']] !== $rank[$y['state']]) {
                return $rank[$x['state']] <=> $rank[$y['state']];
            }

            return ($x['deadlineTs'] ?? PHP_INT_MAX) <=> ($y['deadlineTs'] ?? PHP_INT_MAX);
        });
        $rows = array_slice($rows, 0, 200);

        $pending = array_values(array_filter($rows, fn ($r) => in_array($r['state'], ['new', 'active'], true)));
        $counts = [
            'total' => count($rows),
            'exercises' => count(array_filter($rows, fn ($r) => $r['kind'] === 'exercise')),
            'exams' => count(array_filter($rows, fn ($r) => $r['kind'] === 'exam')),
            'completed' => count(array_filter($rows, fn ($r) => $r['state'] === 'completed')),
            'completedExercises' => count(array_filter($rows, fn ($r) => $r['state'] === 'completed' && $r['kind'] === 'exercise')),
            'completedExams' => count(array_filter($rows, fn ($r) => $r['state'] === 'completed' && $r['kind'] === 'exam')),
            'submitted' => count(array_filter($rows, fn ($r) => $r['state'] === 'submitted')),
            'pendingExercises' => count(array_filter($pending, fn ($r) => $r['kind'] === 'exercise')),
            'pendingExams' => count(array_filter($pending, fn ($r) => $r['kind'] === 'exam')),
            'pending' => count($pending),
            'overdue' => count(array_filter($pending, fn ($r) => $r['overdue'])),
        ];

        return ['rows' => $rows, 'counts' => $counts, 'pending' => $pending];
    }

    // ═════════════════════════════ KẾT QUẢ ═════════════════════════════

    /**
     * "Kết quả & lịch sử nộp" (StudentContent source mới): mỗi dòng là một lần nộp — chấm tròn trạng thái,
     * tên bài, "Nộp lúc HH:MM · ngôn ngữ/loại", trạng thái chấm, điểm. Gộp bài lập trình luyện tập
     * (attempt_answers) và đề đã nộp (attempts).
     *
     * @return array<string,mixed>
     */
    public function results(User $student): array
    {
        $rows = [];

        $attempts = Attempt::query()
            ->where('user_id', $student->id)
            ->whereNotNull('submitted_at')
            ->with('assessment:id,title,total_points')
            ->latest('submitted_at')
            ->limit(120)
            ->get();

        foreach ($attempts as $a) {
            $pending = $a->total_score === null;
            $max = $a->assessment?->total_points;
            $rows[] = [
                'ts' => $a->submitted_at?->getTimestamp() ?? 0,
                'title' => $a->assessment->title ?? 'Bài đã nộp',
                'when' => 'Nộp lúc '.$a->submitted_at?->format('H:i d/m/Y').' · Đề',
                'status' => $pending ? 'Đang chấm' : ($a->is_provisional ? 'Tạm tính' : 'Đã chấm'),
                'tone' => $pending ? 'amber' : ($a->is_provisional ? 'amber' : 'emerald'),
                'score' => $pending ? '—' : $this->num((float) $a->total_score).($max ? ' / '.$this->num((float) $max) : ''),
                'url' => route('student.assessment.result', $a->id),
                'score_raw' => $pending ? null : (float) $a->total_score,
            ];
        }

        // Bài lập trình luyện tập (từng lần nộp gần nhất của mỗi câu).
        $subs = AttemptAnswer::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('attempts.user_id', $student->id)
            ->where('attempt_answers.submission_count', '>', 0)
            ->select('attempt_answers.*')
            ->orderByDesc('attempt_answers.updated_at')
            ->limit(120)
            ->get();
        if ($subs->isNotEmpty()) {
            $qs = Question::query()->whereIn('id', $subs->pluck('question_id')->unique())->get(['id', 'title', 'points'])->keyBy('id');
            foreach ($subs as $sub) {
                $q = $qs->get($sub->question_id);
                $final = $sub->verdict instanceof VerdictStatus ? $sub->verdict->isFinal() : true;
                $ok = $sub->verdict === VerdictStatus::Accepted;
                $lang = $sub->language ? ' · '.strtoupper((string) $sub->language) : '';
                $rows[] = [
                    'ts' => $sub->updated_at?->getTimestamp() ?? 0,
                    'title' => $q->title ?? 'Bài luyện tập',
                    'when' => 'Nộp lúc '.$sub->updated_at?->format('H:i d/m/Y').$lang,
                    'status' => $sub->verdict instanceof VerdictStatus ? ($final ? ($ok ? 'Accepted' : $this->verdictShort($sub->verdict)) : 'Đang chấm') : 'Đã nộp',
                    'tone' => ! $final ? 'amber' : ($ok ? 'emerald' : 'rose'),
                    'score' => $sub->score !== null && $final ? $this->num((float) $sub->score).' / '.$this->num((float) ($q->points ?? 0)) : '—',
                    'url' => route('student.practice.index', ['tab' => 'history']),
                    'score_raw' => $sub->score !== null && $final ? (float) $sub->score : null,
                ];
            }
        }

        usort($rows, fn ($x, $y) => $y['ts'] <=> $x['ts']);
        $rows = array_slice($rows, 0, 150);

        $scored = array_values(array_filter($rows, fn ($r) => $r['score_raw'] !== null));
        $accepted = count(array_filter($rows, fn ($r) => $r['status'] === 'Accepted'));
        $waiting = count(array_filter($rows, fn ($r) => $r['status'] === 'Đang chấm'));

        return [
            'rows' => $rows,
            'count' => count($rows),
            'accepted' => $accepted,
            'waiting' => $waiting,
            'average' => $scored ? round(array_sum(array_column($scored, 'score_raw')) / count($scored), 1) : null,
        ];
    }

    private function verdictShort(VerdictStatus $v): string
    {
        return match ($v) {
            VerdictStatus::WrongAnswer => 'Wrong answer',
            VerdictStatus::TimeLimitExceeded => 'Time limit',
            VerdictStatus::MemoryLimitExceeded => 'Memory limit',
            VerdictStatus::RuntimeError => 'Runtime error',
            VerdictStatus::CompileError => 'Compile error',
            VerdictStatus::SystemError => 'Lỗi hệ thống',
            default => $v->label(),
        };
    }

    // ═════════════════════════════ LỚP HỌC / TỔNG QUAN ═════════════════════════════

    private const COVERS = ['course-img-1.jpg', 'course-img-2.jpg', 'course-img-3.jpg', 'course-img-4.jpg', 'course-img-5.jpg'];
    private const TONES = ['blue', 'violet', 'teal'];

    /**
     * Thẻ "Lớp đang tham gia" (StudentClassesPanel.jsx): mã lớp, tên, hình thức, giáo viên, lịch học
     * hằng tuần, tóm tắt bài được giao của lớp, nút Vào lớp.
     *
     * @return list<array<string,mixed>>
     */
    public function classes(User $student, ?array $assignmentRows = null): array
    {
        $enrollments = ClassEnrollment::query()
            ->where('student_id', $student->id)->where('status', 'active')
            ->with(['classRoom.course', 'classRoom.teachers'])->get()
            ->filter(fn ($e) => $e->classRoom !== null)->values();
        if ($enrollments->isEmpty()) {
            return [];
        }

        $assignmentRows ??= $this->assignments($student)['rows'];
        $out = [];
        foreach ($enrollments as $i => $e) {
            $c = $e->classRoom;
            $teacher = $c->teachers->first();
            $mine = array_values(array_filter($assignmentRows, fn ($r) => $r['classId'] === (int) $c->id));
            $pend = array_filter($mine, fn ($r) => in_array($r['state'], ['new', 'active'], true));
            $out[] = [
                'id' => (int) $c->id,
                'code' => $c->code ?: 'LỚP-'.$c->id,
                'title' => $c->course->title ?? $c->name,
                'className' => $c->name,
                'tone' => self::TONES[$i % 3],
                'cover' => ($c->course?->coverUrl()) ?: asset('assets/'.self::COVERS[$i % count(self::COVERS)]),
                'format' => ClassRoom::supportsDisplayFields()
                    ? (trim((string) ($c->format ?? '')) ?: (trim((string) ($c->location ?? '')) ?: 'Trực tuyến'))
                    : 'Trực tuyến',
                'teacher' => $teacher?->name ?? 'Chưa phân công',
                'teacherAvatar' => $teacher?->avatarUrl(),
                'teacherInitial' => mb_strtoupper(mb_substr($teacher?->name ?? '?', 0, 1)),
                'schedule' => self::scheduleText($c->schedule) ?: 'Chưa có lịch cố định',
                'assignTotal' => count($mine),
                'pendingExercises' => count(array_filter($pend, fn ($r) => $r['kind'] === 'exercise')),
                'pendingExams' => count(array_filter($pend, fn ($r) => $r['kind'] === 'exam')),
                'completed' => count(array_filter($mine, fn ($r) => $r['state'] === 'completed')),
                'submitted' => count(array_filter($mine, fn ($r) => $r['state'] === 'submitted')),
                'url' => route('student.classes.show', $c->id),
            ];
        }

        return $out;
    }

    /** Dữ liệu trang Tổng quan (StudentOverview.jsx + StudentConnections.jsx). @return array<string,mixed> */
    public function overview(User $student): array
    {
        $a = $this->assignments($student);
        $classes = $this->classes($student, $a['rows']);
        $classIds = array_column($classes, 'id');

        $weekday = ['CN', 'Th 2', 'Th 3', 'Th 4', 'Th 5', 'Th 6', 'Th 7'];
        $sessions = [];
        if ($classIds) {
            $teacherByClass = collect($classes)->keyBy('id');
            $list = app(ClassSessionRepositoryInterface::class)->upcomingForClassRoomIds($classIds, 3);
            foreach ($list as $i => $s) {
                $cls = $teacherByClass->get($s->class_room_id);
                $sessions[] = [
                    'date' => $weekday[(int) $s->starts_at->format('w')].', '.$s->starts_at->format('d/m'),
                    'time' => $s->starts_at->format('H:i').($s->ends_at ? ' – '.$s->ends_at->format('H:i') : ''),
                    'title' => $s->topic ?: ($cls['title'] ?? ($s->classRoom->name ?? 'Buổi học')),
                    'teacher' => $cls['teacher'] ?? null,
                    'ongoing' => $s->starts_at->isPast() && ($s->ends_at === null || $s->ends_at->isFuture()),
                    'url' => route('student.classes.show', $s->class_room_id),
                ];
            }
        }

        $teachers = array_map(fn ($c) => [
            'name' => $c['teacher'], 'avatar' => $c['teacherAvatar'], 'initial' => $c['teacherInitial'],
            'title' => $c['title'], 'url' => $c['url'],
        ], array_values(array_filter($classes, fn ($c) => $c['teacher'] !== 'Chưa phân công')));

        $links = ParentLink::query()->where('student_user_id', $student->id)->where('status', 'verified')
            ->with('parent:id,name,email,avatar_path,status')->get();
        $parents = $links->map(fn (ParentLink $l) => [
            'name' => $l->parent?->name ?? 'Tài khoản không còn khả dụng',
            'relationship' => $this->relationshipOf($l),
            'email' => $l->parent?->email,
            'active' => ($l->parent?->status ?? 'active') === 'active',
        ])->values()->all();

        return [
            'counts' => $a['counts'],
            'pending' => array_slice($a['pending'], 0, 3),
            'sessions' => $sessions,
            'teachers' => $teachers,
            'parents' => $parents,
        ];
    }

    /** Mối quan hệ lưu ở parent_links.relationship (nếu đã chạy migration), không có thì "Phụ huynh". */
    public function relationshipOf(ParentLink $link): string
    {
        $v = $link->getAttribute('relationship');

        return filled($v) ? (string) $v : 'Phụ huynh';
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

    /** ClassRoom.schedule là mảng (cast array) có khoá 'note'; chấp nhận cả chuỗi cũ. */
    private static function scheduleText(mixed $schedule): string
    {
        if (is_array($schedule)) {
            $schedule = $schedule['note'] ?? '';
        }

        return is_scalar($schedule) ? trim((string) $schedule) : '';
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
