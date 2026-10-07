<?php

namespace App\Services\Public;

use App\Enums\AttemptStatus;
use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\PracticeAssignment;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 7/10 (khách: "học sinh có thêm phần bài được giao, giáo viên giao bài và xem nhật ký nộp
 * bài") — toàn bộ LOGIC giao bài/giao đề riêng từng học sinh ở trang Luyện tập, dựng theo
 * education-main (QuickAssignButton, AssignmentManagement, AssignmentResult).
 *
 * Bản mẫu lưu tất cả trong localStorage và dùng vai trò giả; ở đây lưu trong bảng
 * practice_assignments và lấy kết quả từ bài làm THẬT (attempt_answers / attempts), nên học
 * sinh nộp bài xong là giáo viên thấy ngay, không phụ thuộc trình duyệt.
 *
 * Quy ước "đã nộp": chỉ tính bài làm xảy ra SAU lúc giao (bản mẫu: "kể từ khi giao") — bài đã
 * làm từ trước không tự động tính là hoàn thành lượt giao mới, nếu không giáo viên giao một bài
 * học sinh từng giải sẽ thấy ngay "Hoàn thành" dù em chưa làm lại gì.
 */
class PracticeAssignmentService
{
    /** Trần số dòng cho danh sách quản lý, để trang không phình vô hạn. */
    private const MANAGED_LIMIT = 300;

    /** Cắt bài làm đưa ra khung chi tiết cho gọn (ký tự). */
    private const CODE_PREVIEW_CHARS = 6000;

    // ───────────────────────── Vai trò ─────────────────────────

    public function isAdmin(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }

    public function isTeacher(?User $user): bool
    {
        return $user !== null && $user->hasRole(Role::TEACHER);
    }

    public function isStudent(?User $user): bool
    {
        return $user !== null && $user->hasRole(Role::STUDENT);
    }

    /** Giáo viên và admin được giao bài / xem lượt đã giao. */
    public function canAssign(?User $user): bool
    {
        return $this->isAdmin($user) || $this->isTeacher($user);
    }

    /** Bảng đã được tạo trên máy chủ chưa (deploy mã trước khi chạy migrate vẫn không vỡ trang). */
    public function isReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('practice_assignments');
    }

    /**
     * Khối mô tả quyền đưa sang view Luyện tập.
     *
     * @return array{role: string, canViewAssigned: bool, canManage: bool, canAssign: bool}
     */
    public function scopeFor(?User $viewer): array
    {
        $role = match (true) {
            $viewer === null => 'guest',
            $this->isAdmin($viewer) => 'admin',
            $this->isTeacher($viewer) => 'teacher',
            $this->isStudent($viewer) => 'student',
            default => 'member',
        };

        $ready = $this->isReady();

        return [
            'role' => $role,
            'canViewAssigned' => $ready && $role === 'student',
            'canManage' => $ready && in_array($role, ['admin', 'teacher'], true),
            'canAssign' => $ready && in_array($role, ['admin', 'teacher'], true),
        ];
    }

    // ───────────────────────── Giao bài ─────────────────────────

    /**
     * Giao một bài/đề cho một học sinh.
     *
     * @throws ValidationException
     */
    public function assign(User $by, string $type, int $subjectId, string $account, Carbon $deadline): PracticeAssignment
    {
        if (! $this->canAssign($by)) {
            throw ValidationException::withMessages(['account' => 'Bạn không có quyền giao nội dung này.']);
        }

        if (! in_array($type, [PracticeAssignment::TYPE_PROBLEM, PracticeAssignment::TYPE_EXAM], true)) {
            throw ValidationException::withMessages(['account' => 'Không tìm thấy nội dung cần giao.']);
        }

        if (! $this->subjectExists($type, $subjectId)) {
            throw ValidationException::withMessages(['account' => 'Không tìm thấy nội dung cần giao.']);
        }

        if ($deadline->lte(now())) {
            throw ValidationException::withMessages(['deadline' => 'Hạn nộp phải ở sau thời điểm hiện tại.']);
        }

        $student = $this->findStudent($account);

        if ($student === null) {
            throw ValidationException::withMessages([
                'account' => 'Không tìm thấy học sinh với tài khoản này. Hãy nhập đúng email hoặc số điện thoại học sinh đã đăng ký.',
            ]);
        }

        // Giao lại cùng một nội dung cho cùng một học sinh -> cập nhật hạn, không sinh dòng mới.
        return PracticeAssignment::query()->updateOrCreate(
            [
                'assigned_by' => $by->id,
                'student_id' => $student->id,
                'type' => $type,
                'subject_id' => $subjectId,
            ],
            ['deadline_at' => $deadline],
        );
    }

    /** Trần số học sinh cho MỘT lần giao, để một cú bấm không sinh hàng nghìn dòng. */
    public const ASSIGN_MAX_STUDENTS = 50;

    /** Số gợi ý tối đa của ô tìm học sinh. */
    private const SEARCH_LIMIT = 20;

    /**
     * Giao một bài/đề cho NHIỀU học sinh cùng lúc (popup chọn nhiều học sinh).
     * Tất cả hoặc không: kiểm tra xong hết mới ghi, nên một id sai không để lại nửa chừng.
     *
     * @param  array<int, int|string>  $studentIds
     * @return Collection<int, PracticeAssignment>
     *
     * @throws ValidationException
     */
    public function assignMany(User $by, string $type, int $subjectId, array $studentIds, Carbon $deadline): Collection
    {
        if (! $this->canAssign($by)) {
            throw ValidationException::withMessages(['students' => 'Bạn không có quyền giao nội dung này.']);
        }

        if (! in_array($type, [PracticeAssignment::TYPE_PROBLEM, PracticeAssignment::TYPE_EXAM], true)
            || ! $this->subjectExists($type, $subjectId)) {
            throw ValidationException::withMessages(['students' => 'Không tìm thấy nội dung cần giao.']);
        }

        if ($deadline->lte(now())) {
            throw ValidationException::withMessages(['deadline' => 'Hạn nộp phải ở sau thời điểm hiện tại.']);
        }

        $ids = collect($studentIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['students' => 'Chọn ít nhất 1 học sinh.']);
        }

        if ($ids->count() > self::ASSIGN_MAX_STUDENTS) {
            throw ValidationException::withMessages(['students' => 'Mỗi lần giao tối đa '.self::ASSIGN_MAX_STUDENTS.' học sinh.']);
        }

        // Chỉ học sinh THẬT mới nhận được bài — id gửi lên có thể bị sửa tay.
        $students = User::query()
            ->whereIn('id', $ids->all())
            ->whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))
            ->get(['id', 'name', 'email', 'phone'])
            ->keyBy('id');

        if ($students->count() !== $ids->count()) {
            throw ValidationException::withMessages(['students' => 'Có học sinh không hợp lệ. Hãy chọn lại danh sách.']);
        }

        return DB::transaction(function () use ($by, $type, $subjectId, $deadline, $ids, $students) {
            return $ids->map(function (int $id) use ($by, $type, $subjectId, $deadline, $students) {
                $assignment = PracticeAssignment::query()->updateOrCreate(
                    [
                        'assigned_by' => $by->id,
                        'student_id' => $id,
                        'type' => $type,
                        'subject_id' => $subjectId,
                    ],
                    ['deadline_at' => $deadline],
                );
                $assignment->setRelation('student', $students->get($id));

                return $assignment;
            });
        });
    }

    /**
     * Gợi ý học sinh cho ô chọn (Select2): khớp tên / email / số điện thoại. Chưa gõ gì thì trả
     * những học sinh đăng ký gần nhất để bấm mở ô là có danh sách ngay.
     *
     * @return array<int, array{id: int, name: string, email: ?string, phone: ?string}>
     */
    public function searchStudents(string $term): array
    {
        $term = trim($term);

        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))
            ->select(['id', 'name', 'email', 'phone']);

        if ($term !== '') {
            // Escape ký tự đặc biệt của LIKE để "%" hay "_" gõ vào không khớp mọi dòng.
            $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
            $digits = preg_replace('/[\s.\-()]/', '', $term);

            $query->where(function ($q) use ($like, $digits) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);

                if ($digits !== '' && ctype_digit(ltrim($digits, '+'))) {
                    $q->orWhere('phone', 'like', '%'.addcslashes($digits, '%_\\').'%');
                }
            })->orderByRaw('LOWER(name) asc');
        } else {
            $query->orderByDesc('id');
        }

        return $query->limit(self::SEARCH_LIMIT)->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => (string) $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
            ])->all();
    }

    /**
     * Học sinh nhận bài được nhận diện bằng EMAIL hoặc SỐ ĐIỆN THOẠI — hệ thống không có tên
     * đăng nhập riêng (bản mẫu dùng "tài khoản" dạng minh.anh10a1).
     */
    public function findStudent(string $account): ?User
    {
        $account = mb_strtolower(trim($account));

        if ($account === '') {
            return null;
        }

        $phone = preg_replace('/[\s.\-()]/', '', $account);

        return User::query()
            ->where(function ($q) use ($account, $phone) {
                $q->whereRaw('LOWER(email) = ?', [$account]);

                if ($phone !== '' && preg_match('/^\+?\d{8,15}$/', $phone)) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))
            ->first();
    }

    private function subjectExists(string $type, int $subjectId): bool
    {
        if ($type === PracticeAssignment::TYPE_PROBLEM) {
            return Question::query()
                ->whereKey($subjectId)
                ->where('status', 'published')
                ->exists();
        }

        return Assessment::query()
            ->whereKey($subjectId)
            ->where('type', 'practice')
            ->where('status', 'published')
            ->exists();
    }

    // ───────────────────────── Phía học sinh ─────────────────────────

    /**
     * Bài/đề ĐƯỢC GIAO cho một học sinh, kèm trạng thái hoàn thành.
     *
     * @return array{problem: array<int, array<string, mixed>>, exam: array<int, array<string, mixed>>}
     */
    public function forStudent(User $student): array
    {
        $out = ['problem' => [], 'exam' => []];

        if (! $this->isReady()) {
            return $out;
        }

        $assignments = PracticeAssignment::query()
            ->where('student_id', $student->id)
            ->with('assigner:id,name')
            ->orderByDesc('id')
            ->get();

        if ($assignments->isEmpty()) {
            return $out;
        }

        $progress = $this->progressFor($assignments);

        foreach ($assignments as $assignment) {
            // Cùng một nội dung được nhiều thầy cô giao: giữ lượt giao MỚI NHẤT (đã sắp id giảm dần).
            if (isset($out[$assignment->type][$assignment->subject_id])) {
                continue;
            }

            $p = $progress[$assignment->id] ?? null;
            $status = $p['status'] ?? 'unsubmitted';

            $out[$assignment->type][$assignment->subject_id] = [
                'id' => $assignment->id,
                'teacher' => $assignment->assigner?->name ?? 'Giáo viên',
                'group' => 'Giao riêng',
                'assignedAt' => $assignment->created_at?->format('d/m/Y'),
                'deadline' => $assignment->deadline_at?->format('d/m/Y · H:i'),
                'deadlineTs' => $assignment->deadline_at?->getTimestamp(),
                'overdue' => (bool) ($assignment->deadline_at?->isPast() && in_array($status, ['unsubmitted', 'overdue'], true)),
                // 3 trạng thái bản mẫu dùng cho học sinh: Hoàn thành / Chờ chấm / Chưa hoàn thành.
                'status' => match ($status) {
                    'completed' => 'completed',
                    'pending' => 'pending',
                    default => 'incomplete',
                },
                'late' => (bool) ($p['late'] ?? false),
                'score' => $p['score'] ?? null,
                'maxScore' => $p['maxScore'] ?? null,
                'resultLabel' => $p['resultLabel'] ?? null,
                'submittedAt' => $p['submittedAtLabel'] ?? null,
            ];
        }

        return $out;
    }

    // ───────────────────────── Phía giáo viên / admin ─────────────────────────

    /**
     * Danh sách "Bài đã giao" / "Đề đã giao": mỗi dòng là một học sinh nhận bài.
     * Giáo viên chỉ thấy lượt MÌNH giao; admin thấy tất cả.
     *
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>, total: int}
     */
    public function managed(User $viewer, string $type): array
    {
        $empty = ['rows' => [], 'counts' => ['completed' => 0, 'pending' => 0, 'unsubmitted' => 0, 'overdue' => 0, 'late' => 0], 'total' => 0];

        if (! $this->isReady() || ! $this->canAssign($viewer)) {
            return $empty;
        }

        $query = PracticeAssignment::query()
            ->where('type', $type)
            ->with(['student:id,name,email,phone', 'assigner:id,name'])
            ->orderByDesc('id')
            ->limit(self::MANAGED_LIMIT);

        if (! $this->isAdmin($viewer)) {
            $query->where('assigned_by', $viewer->id);
        }

        $assignments = $query->get();

        if ($assignments->isEmpty()) {
            return $empty;
        }

        $ids = $assignments->pluck('subject_id')->unique()->all();
        $titles = $type === PracticeAssignment::TYPE_PROBLEM
            ? Question::query()->whereIn('id', $ids)->get(['id', 'title', 'code'])->mapWithKeys(fn ($q) => [$q->id => ['title' => $q->title, 'code' => $q->code]])
            : Assessment::query()->whereIn('id', $ids)->get(['id', 'title', 'exam_code'])->mapWithKeys(fn ($a) => [$a->id => ['title' => $a->title, 'code' => $a->exam_code ?: '#'.$a->id]]);

        $progress = $this->progressFor($assignments, withDetail: true);

        $counts = $empty['counts'];
        $rows = [];

        foreach ($assignments as $a) {
            $p = $progress[$a->id] ?? ['status' => 'unsubmitted'];
            $meta = $titles->get($a->subject_id) ?? ['title' => 'Nội dung đã bị xoá', 'code' => '#'.$a->subject_id];

            $counts[$p['status']] = ($counts[$p['status']] ?? 0) + 1;
            if (! empty($p['late'])) {
                $counts['late']++;
            }

            $rows[] = [
                'id' => $a->id,
                'subjectId' => $a->subject_id,
                'title' => $meta['title'],
                'code' => $meta['code'],
                'studentName' => $a->student?->name ?? '—',
                'account' => $a->student?->email ?: ($a->student?->phone ?: '—'),
                'teacher' => $a->assigner?->name ?? '—',
                'assignedAt' => $a->created_at?->format('d/m/Y H:i'),
                'assignedTs' => $a->created_at?->getTimestamp() ?? 0,
                'deadline' => $a->deadline_at?->format('d/m/Y H:i'),
                'deadlineTs' => $a->deadline_at?->getTimestamp() ?? 0,
                'status' => $p['status'],
                'late' => (bool) ($p['late'] ?? false),
                'scoreLabel' => $p['scoreLabel'] ?? null,
                'scoreRatio' => $p['scoreRatio'] ?? null,
                'attempts' => (int) ($p['attempts'] ?? 0),
                'submittedAt' => $p['submittedAtLabel'] ?? null,
                'submittedTs' => $p['submittedTs'] ?? 0,
                'startedAt' => $p['startedAtLabel'] ?? null,
                'gradedAt' => $p['gradedAtLabel'] ?? null,
                'duration' => $p['durationLabel'] ?? null,
                'resultLabel' => $p['resultLabel'] ?? null,
                'language' => $p['language'] ?? null,
                'source' => $p['codePreview'] ?? null,
                'tests' => $p['testsLabel'] ?? null,
            ];
        }

        return ['rows' => $rows, 'counts' => $counts, 'total' => count($rows)];
    }

    // ───────────────────────── Tiến độ ─────────────────────────

    /**
     * Tính tiến độ cho từng lượt giao từ bài làm thật.
     *
     * @param  Collection<int, PracticeAssignment>  $assignments
     * @return array<int, array<string, mixed>>  khoá = id lượt giao
     */
    private function progressFor(Collection $assignments, bool $withDetail = false): array
    {
        $result = [];

        $problemAssignments = $assignments->where('type', PracticeAssignment::TYPE_PROBLEM);
        $examAssignments = $assignments->where('type', PracticeAssignment::TYPE_EXAM);

        if ($problemAssignments->isNotEmpty()) {
            $result += $this->problemProgress($problemAssignments, $withDetail);
        }

        if ($examAssignments->isNotEmpty()) {
            $result += $this->examProgress($examAssignments, $withDetail);
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function problemProgress(Collection $assignments, bool $withDetail): array
    {
        $studentIds = $assignments->pluck('student_id')->unique()->all();
        $questionIds = $assignments->pluck('subject_id')->unique()->all();

        $answers = AttemptAnswer::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->whereIn('attempts.user_id', $studentIds)
            ->whereIn('attempt_answers.question_id', $questionIds)
            ->where(function ($q) {
                $q->where('attempt_answers.submission_count', '>', 0)
                    ->orWhereNotNull('attempt_answers.graded_at')
                    ->orWhereNotNull('attempt_answers.score');
            })
            ->select('attempt_answers.*', 'attempts.user_id as owner_id')
            ->get()
            ->groupBy(fn ($row) => $row->owner_id.':'.$row->question_id);

        $points = Question::query()->whereIn('id', $questionIds)->pluck('points', 'id');

        $out = [];

        foreach ($assignments as $assignment) {
            $rows = ($answers->get($assignment->student_id.':'.$assignment->subject_id) ?? collect())
                ->filter(fn ($r) => $r->updated_at !== null && $r->updated_at->gte($assignment->created_at))
                ->sortByDesc('updated_at')
                ->values();

            $latest = $rows->first();
            $max = (float) ($points[$assignment->subject_id] ?? 0);

            if ($latest === null) {
                $out[$assignment->id] = ['status' => $this->emptyStatus($assignment)];

                continue;
            }

            $isFinal = $latest->verdict instanceof VerdictStatus ? $latest->verdict->isFinal() : true;
            $score = $latest->score !== null ? (float) $latest->score : null;
            $submittedAt = $latest->updated_at;

            $entry = [
                'status' => $isFinal ? 'completed' : 'pending',
                'late' => $submittedAt->gt($assignment->deadline_at),
                'score' => $score,
                'maxScore' => $max,
                'scoreRatio' => $isFinal && $score !== null && $max > 0 ? $score / $max : null,
                'scoreLabel' => $isFinal && $score !== null ? $this->num($score).'/'.$this->num($max) : null,
                'resultLabel' => $isFinal ? $this->verdictLabel($latest) : 'Chờ chấm',
                'attempts' => (int) $rows->sum(fn ($r) => max(1, (int) $r->submission_count)),
                'submittedAtLabel' => $submittedAt->format('d/m/Y H:i'),
                'submittedTs' => $submittedAt->getTimestamp(),
            ];

            if ($withDetail) {
                $entry['language'] = $latest->language;
                $entry['gradedAtLabel'] = $isFinal ? ($latest->graded_at ?? $submittedAt)->format('d/m/Y H:i') : null;
                $entry['codePreview'] = $latest->code_source !== null
                    ? mb_substr((string) $latest->code_source, 0, self::CODE_PREVIEW_CHARS)
                    : (is_array($latest->answer) ? json_encode($latest->answer, JSON_UNESCAPED_UNICODE) : null);

                if (AttemptAnswer::supportsTestCounts() && ($latest->total_tests ?? 0) > 0) {
                    $entry['testsLabel'] = (int) $latest->passed_tests.'/'.(int) $latest->total_tests.' test';
                }
            }

            $out[$assignment->id] = $entry;
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function examProgress(Collection $assignments, bool $withDetail): array
    {
        $studentIds = $assignments->pluck('student_id')->unique()->all();
        $examIds = $assignments->pluck('subject_id')->unique()->all();

        $attempts = Attempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('assessment_id', $examIds)
            ->whereNotNull('submitted_at')
            ->get()
            ->groupBy(fn ($a) => $a->user_id.':'.$a->assessment_id);

        $totals = Assessment::query()->whereIn('id', $examIds)->pluck('total_points', 'id');

        $out = [];

        foreach ($assignments as $assignment) {
            $rows = ($attempts->get($assignment->student_id.':'.$assignment->subject_id) ?? collect())
                ->filter(fn ($a) => $a->submitted_at->gte($assignment->created_at))
                ->sortByDesc('submitted_at')
                ->values();

            $latest = $rows->first();
            $max = (float) ($totals[$assignment->subject_id] ?? 0);

            if ($latest === null) {
                $out[$assignment->id] = ['status' => $this->emptyStatus($assignment)];

                continue;
            }

            $pending = $latest->is_provisional
                || $latest->total_score === null
                || $latest->status === AttemptStatus::Grading;

            $score = $latest->total_score !== null ? (float) $latest->total_score : null;
            $ratio = ! $pending && $score !== null && $max > 0 ? $score / $max : null;

            $entry = [
                'status' => $pending ? 'pending' : 'completed',
                'late' => $latest->submitted_at->gt($assignment->deadline_at),
                'score' => $score,
                'maxScore' => $max,
                'scoreRatio' => $ratio,
                'scoreLabel' => ! $pending && $score !== null ? $this->num($score).'/'.$this->num($max) : null,
                'resultLabel' => $pending ? 'Chờ chấm' : $this->ratioLabel($ratio),
                'attempts' => $rows->count(),
                'submittedAtLabel' => $latest->submitted_at->format('d/m/Y H:i'),
                'submittedTs' => $latest->submitted_at->getTimestamp(),
            ];

            if ($withDetail) {
                $entry['startedAtLabel'] = $latest->started_at?->format('d/m/Y H:i');
                $entry['gradedAtLabel'] = $pending ? null : $latest->submitted_at->format('d/m/Y H:i');

                if ($latest->started_at !== null) {
                    $seconds = max(0, $latest->submitted_at->diffInSeconds($latest->started_at, true));
                    $entry['durationLabel'] = intdiv((int) $seconds, 60).' phút '.((int) $seconds % 60).' giây';
                }
            }

            $out[$assignment->id] = $entry;
        }

        return $out;
    }

    private function emptyStatus(PracticeAssignment $assignment): string
    {
        return $assignment->deadline_at->isPast() ? 'overdue' : 'unsubmitted';
    }

    private function verdictLabel(AttemptAnswer $answer): string
    {
        if ($answer->verdict === VerdictStatus::Accepted) {
            return 'Đúng toàn bộ';
        }

        if ((float) ($answer->score ?? 0) > 0) {
            return 'Đúng một phần';
        }

        return 'Chưa đúng';
    }

    private function ratioLabel(?float $ratio): string
    {
        return match (true) {
            $ratio === null => 'Chưa có điểm',
            $ratio >= 0.999 => 'Đúng toàn bộ',
            $ratio > 0 => 'Đúng một phần',
            default => 'Chưa đúng',
        };
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
