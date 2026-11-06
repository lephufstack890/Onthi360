<?php

namespace App\Services\Teacher;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Attendance;
use App\Models\ClassAnnouncement;
use App\Models\ClassEnrollment;
use App\Models\ClassGrade;
use App\Models\ClassMaterial;
use App\Models\ClassProgressStep;
use App\Models\ClassRoom;
use App\Models\ClassSession;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ClassSpaceNotice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 6/11 (khách: "lớp phải là một giao diện riêng — 8 tab: Tổng quan, Người tổ chức, Điểm danh, Tài liệu, Bài tập / Đề,
 * Tiến trình dạy học, Kết quả học tập, Thông báo") — dữ liệu + thao tác MỚI của không gian lớp. Phần đã có (tài liệu, giao
 * đề, thông báo, buổi học, thêm / duyệt / gỡ học sinh) vẫn chạy qua ClassRoomService / ScheduleService / TeacherWorkspaceService
 * như cũ; lớp này chỉ thêm:
 *  · vai trò giáo viên trong lớp (bảng class_teachers có sẵn: main | co_teacher | assistant) + quyền theo vai trò;
 *  · sổ điểm danh dạng bảng (lưu từng ô), xuất CSV;
 *  · số bài đã nộp + nhắc nộp bài;
 *  · tiến trình dạy học (class_progress_steps);
 *  · bảng điểm thang 10 (điểm tự động từ bài làm, giáo viên sửa / chấm đè + nhận xét — class_grades), phiếu kết quả.
 */
class ClassSpaceService
{
    public const TABS = [
        'overview' => ['Tổng quan', 'home'],
        'organizers' => ['Người tổ chức', 'user-round'],
        'attendance' => ['Điểm danh', 'list-checks'],
        'materials' => ['Tài liệu', 'book-open'],
        'assign' => ['Bài tập / Đề', 'clipboard-list'],
        'progress' => ['Tiến trình dạy học', 'layers'],
        'results' => ['Kết quả học tập', 'bar-chart-3'],
        'notices' => ['Thông báo', 'megaphone'],
    ];

    /** Tên tab cũ → tab mới (link cũ, redirect cũ vẫn mở đúng chỗ). */
    private const LEGACY_TABS = ['schedule' => 'attendance', 'members' => 'attendance'];

    public const ROLE_LABELS = ['main' => 'Giáo viên chính', 'co_teacher' => 'Đồng giảng dạy', 'assistant' => 'Trợ giảng', 'admin' => 'Quản trị'];

    /** Việc được làm theo vai trò. Giáo viên chính + quản trị: tất cả. */
    public const ABILITIES = [
        'attendance' => 'Điểm danh, nhận xét buổi học',
        'sessions' => 'Thêm / sửa buổi học',
        'materials' => 'Thêm tài liệu, giao bài tập / đề',
        'grade' => 'Chấm điểm, nhận xét kết quả',
        'progress' => 'Thêm & mở khoá tiến trình dạy học',
        'notices' => 'Gửi thông báo cho lớp',
        'students' => 'Thêm / duyệt / gỡ học sinh',
        'teachers' => 'Thêm / gỡ giáo viên của lớp',
    ];

    private const ROLE_ABILITIES = [
        'co_teacher' => ['attendance', 'sessions', 'materials', 'grade', 'progress', 'notices', 'students'],
        'assistant' => ['attendance', 'grade'],
    ];

    public const STATUS_LABELS = ['present' => 'Có mặt', 'late' => 'Muộn', 'excused' => 'Có phép', 'absent' => 'Vắng'];

    public static function normalizeTab(?string $tab): string
    {
        $tab = self::LEGACY_TABS[$tab] ?? $tab;

        return array_key_exists((string) $tab, self::TABS) ? $tab : 'overview';
    }

    // ───────────────────────── Vai trò & quyền ─────────────────────────

    /** main | co_teacher | assistant | admin | null. Quản trị thật (kể cả đang xem như giáo viên) = toàn quyền. */
    public function roleOf(ClassRoom $classRoom, User $user): ?string
    {
        $role = DB::table('class_teachers')->where('class_room_id', $classRoom->id)->where('user_id', $user->id)->value('role');
        if ($role === 'main') {
            return 'main';
        }
        if ($user->isRealAdmin() || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            return 'admin';
        }

        return $role === null ? null : (in_array($role, ['co_teacher', 'assistant'], true) ? $role : 'co_teacher');
    }

    public function can(ClassRoom $classRoom, User $user, string $ability): bool
    {
        $role = $this->roleOf($classRoom, $user);

        return match ($role) {
            'main', 'admin' => true,
            null => false,
            default => in_array($ability, self::ROLE_ABILITIES[$role] ?? [], true),
        };
    }

    /** Dùng ở đầu mỗi thao tác: lớp có tồn tại, người dùng có vai trò trong lớp và được làm việc $ability. */
    public function authorize(int|ClassRoom $class, User $user, string $ability): ClassRoom
    {
        $classRoom = $class instanceof ClassRoom ? $class : ClassRoom::query()->findOrFail($class);
        $role = $this->roleOf($classRoom, $user);
        abort_if($role === null, 403, 'Bạn không phụ trách lớp này.');
        abort_unless($this->can($classRoom, $user, $ability), 403,
            'Vai trò "'.(self::ROLE_LABELS[$role] ?? $role).'" không được: '.mb_strtolower(self::ABILITIES[$ability] ?? $ability).'.');

        return $classRoom;
    }

    /** Ma trận quyền (hiển thị ở tab Người tổ chức). */
    public function abilityMatrix(): array
    {
        return collect(self::ABILITIES)->map(fn ($label, $key) => [
            'label' => $label,
            'main' => true,
            'co_teacher' => in_array($key, self::ROLE_ABILITIES['co_teacher'], true),
            'assistant' => in_array($key, self::ROLE_ABILITIES['assistant'], true),
        ])->values()->all();
    }

    // ───────────────────────── Đầu trang lớp ─────────────────────────

    public function header(ClassRoom $classRoom, User $user): array
    {
        $role = $this->roleOf($classRoom, $user);

        return [
            'role' => $role,
            'roleLabel' => self::ROLE_LABELS[$role] ?? '',
            'can' => collect(array_keys(self::ABILITIES))->mapWithKeys(fn ($a) => [$a => $this->can($classRoom, $user, $a)])->all(),
            'teacherCount' => DB::table('class_teachers')->where('class_room_id', $classRoom->id)->count(),
            'pendingCount' => ClassEnrollment::query()->where('class_room_id', $classRoom->id)->where('status', ClassEnrollment::STATUS_PENDING)->count(),
            'gradingCount' => $this->gradingQueueCount($classRoom),
        ];
    }

    private function rosterIds(ClassRoom $classRoom): array
    {
        return ClassEnrollment::query()->where('class_room_id', $classRoom->id)->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->pluck('student_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    private function roster(ClassRoom $classRoom): Collection
    {
        $ids = $this->rosterIds($classRoom);

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'avatar_path']);
    }

    private function person(User $u): array
    {
        $words = preg_split('/\s+/u', trim((string) $u->name)) ?: [];

        return [
            'id' => (int) $u->id,
            'name' => (string) $u->name,
            'initials' => mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, -2)))) ?: '?',
            'avatar' => $u->avatarUrl() ?: null,
            'hue' => (int) $u->id % 8,
        ];
    }

    private function maskContact(?string $email, ?string $phone): string
    {
        $email = (string) $email;
        if ($email !== '' && str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);

            return mb_substr($local, 0, 2).'***@'.$domain;
        }
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 7 ? substr($digits, 0, 3).'****'.substr($digits, -3) : '';
    }

    // ───────────────────────── Tab 1: Tổng quan ─────────────────────────

    public function overview(ClassRoom $classRoom): array
    {
        $rosterIds = $this->rosterIds($classRoom);
        $sessionIds = ClassSession::query()->where('class_room_id', $classRoom->id)->pluck('id');
        $att = Attendance::query()->whereIn('class_session_id', $sessionIds)->whereIn('student_id', $rosterIds)->get(['class_session_id', 'student_id', 'status']);
        $attended = $att->filter(fn ($a) => in_array($this->statusValue($a->status), ['present', 'late'], true))->count();
        $attRate = $att->count() > 0 ? (int) round($attended / $att->count() * 100) : null;

        $upcoming = ClassSession::query()->where('class_room_id', $classRoom->id)->where('ends_at', '>=', now())
            ->orderBy('starts_at')->limit(3)->get()
            ->map(fn (ClassSession $s) => $this->sessionCard($s))->all();

        $steps = ClassProgressStep::isReady() ? ClassProgressStep::query()->where('class_room_id', $classRoom->id)->orderBy('position')->get() : collect();
        $progress = $this->progress($classRoom);

        $closingSoon = Assignment::query()->where('class_room_id', $classRoom->id)
            ->whereNotIn('status', [AssignmentStatus::Draft->value, AssignmentStatus::Archived->value])
            ->whereNotNull('closes_at')->whereBetween('closes_at', [now(), now()->addDays(3)])->count();

        // Cần chú ý: vắng ≥ 2 trong 3 buổi đã điểm danh gần nhất; điểm TB < 5; chưa nộp bài đã đóng.
        $attention = [];
        $lastTaken = Attendance::query()->whereIn('class_session_id', $sessionIds)->select('class_session_id')->distinct()->pluck('class_session_id');
        $lastTaken = ClassSession::query()->whereIn('id', $lastTaken)->orderByDesc('starts_at')->limit(3)->pluck('id');
        if ($lastTaken->isNotEmpty()) {
            $absences = Attendance::query()->whereIn('class_session_id', $lastTaken)->where('status', 'absent')
                ->select('student_id', DB::raw('count(*) as n'))->groupBy('student_id')->having('n', '>=', 2)->pluck('n', 'student_id');
            foreach ($absences as $sid => $n) {
                $attention[(int) $sid][] = ['text' => "Vắng {$n}/".$lastTaken->count().' buổi gần nhất', 'tone' => 'no'];
            }
        }
        $results = $this->results($classRoom);
        foreach ($results['rows'] as $row) {
            if ($row['avg'] !== null && $row['avg'] < 5) {
                $attention[$row['id']][] = ['text' => 'Điểm TB '.number_format($row['avg'], 1).' — dưới trung bình', 'tone' => 'ex'];
            }
            if ($row['missingClosed'] > 0) {
                $attention[$row['id']][] = ['text' => 'Chưa nộp '.$row['missingClosed'].' bài đã hết hạn', 'tone' => 'ex'];
            }
        }
        $people = User::query()->whereIn('id', array_keys($attention))->get(['id', 'name', 'avatar_path'])->keyBy('id');
        $attentionList = collect($attention)->map(fn ($items, $sid) => isset($people[$sid]) ? $this->person($people[$sid]) + ['items' => $items] : null)
            ->filter()->sortByDesc(fn ($p) => count($p['items']))->take(6)->values()->all();

        $notices = ClassAnnouncement::isReady()
            ? ClassAnnouncement::query()->where('class_room_id', $classRoom->id)->where('state', ClassAnnouncement::STATE_PUBLISHED)
                ->orderByDesc('published_at')->limit(3)->get()
                ->map(fn ($n) => ['title' => (string) $n->title, 'meta' => $n->published_at?->copy()->locale('vi')->diffForHumans().' · '.(int) $n->student_count.' học sinh'.($n->parent_count ? ', '.(int) $n->parent_count.' phụ huynh' : '')])->all()
            : [];

        return [
            'students' => count($rosterIds),
            'attRate' => $attRate,
            'stepsOpen' => $steps->where('is_unlocked', true)->count(),
            'stepsTotal' => $steps->count(),
            'currentStep' => optional($steps->where('is_unlocked', true)->sortByDesc('position')->first())->title,
            'grading' => $this->gradingQueueCount($classRoom),
            'closingSoon' => $closingSoon,
            'upcoming' => $upcoming,
            'attention' => $attentionList,
            'steps' => collect($progress['steps'])->where('unlocked', true)->take(5)->values()->all(),
            'notices' => $notices,
        ];
    }

    private function gradingQueueCount(ClassRoom $classRoom): int
    {
        return Attempt::query()->where('class_room_id', $classRoom->id)->whereNotNull('assignment_id')
            ->where('status', 'grading')->count();
    }

    private function sessionCard(ClassSession $s): array
    {
        $now = now();
        [$label, $tone] = match (true) {
            $now->lt($s->starts_at) => ['Sắp diễn ra', 'late'],
            $now->gt($s->ends_at) => ['Đã kết thúc', 'gray'],
            default => ['Đang diễn ra', 'ok'],
        };
        $dow = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][$s->starts_at->dayOfWeek];

        return [
            'id' => $s->id, 'dow' => $dow, 'day' => $s->starts_at->format('d'), 'month' => $s->starts_at->format('m'),
            'topic' => (string) ($s->topic ?: 'Buổi học'), 'location' => (string) $s->location,
            'time' => $s->starts_at->format('H:i').' – '.$s->ends_at->format('H:i'),
            'status' => $label, 'tone' => $tone,
        ];
    }

    private function statusValue($status): string
    {
        return $status instanceof \BackedEnum ? $status->value : (string) $status;
    }

    // ───────────────────────── Tab 2: Người tổ chức ─────────────────────────

    public function organizers(ClassRoom $classRoom, User $viewer): array
    {
        $canManage = $this->can($classRoom, $viewer, 'teachers');
        $teachers = $classRoom->teachers()->orderByRaw("CASE class_teachers.role WHEN 'main' THEN 0 WHEN 'co_teacher' THEN 1 ELSE 2 END")
            ->orderBy('class_teachers.created_at')->get(['users.id', 'users.name', 'users.email', 'users.phone', 'users.avatar_path'])
            ->map(function (User $t) use ($canManage, $viewer) {
                $role = in_array($t->pivot->role, ['main', 'co_teacher', 'assistant'], true) ? $t->pivot->role : 'co_teacher';

                return $this->person($t) + [
                    'role' => $role,
                    'roleLabel' => self::ROLE_LABELS[$role],
                    'contact' => $this->maskContact($t->email, $t->phone),
                    'since' => $t->pivot->created_at?->format('d/m/Y'),
                    'me' => (int) $t->id === (int) $viewer->id,
                    'editable' => $canManage && $role !== 'main',
                ];
            })->values()->all();

        return [
            'teachers' => $teachers,
            'canManage' => $canManage,
            'matrix' => $this->abilityMatrix(),
        ];
    }

    public function searchTeachers(ClassRoom $classRoom, string $q, int $limit = 15): array
    {
        $inClass = DB::table('class_teachers')->where('class_room_id', $classRoom->id)->pluck('user_id')->all();
        $q = trim(mb_substr($q, 0, 100));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $digits = preg_replace('/\D+/', '', $q) ?? '';

        return User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', Role::TEACHER))
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', '!=', 'suspended'))
            ->whereNotIn('id', $inClass)
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($like, $digits) {
                $x->where('name', 'like', $like)->orWhere('email', 'like', $like);
                if (strlen($digits) >= 3) {
                    $x->orWhere('phone', 'like', '%'.$digits.'%');
                }
            }))
            ->orderBy('name')->limit($limit)
            ->get(['id', 'name', 'email', 'phone', 'avatar_path'])
            ->map(fn (User $u) => $this->person($u) + ['contact' => $this->maskContact($u->email, $u->phone)])
            ->all();
    }

    public function addTeacher(ClassRoom $classRoom, User $actor, int $teacherId, string $role): User
    {
        $role = in_array($role, ['co_teacher', 'assistant'], true) ? $role : 'co_teacher';
        $teacher = User::query()->whereKey($teacherId)->whereHas('roles', fn ($r) => $r->where('name', Role::TEACHER))->first();
        if ($teacher === null) {
            throw ValidationException::withMessages(['teacher_id' => 'Không tìm thấy tài khoản giáo viên này.']);
        }
        if (DB::table('class_teachers')->where('class_room_id', $classRoom->id)->where('user_id', $teacher->id)->exists()) {
            throw ValidationException::withMessages(['teacher_id' => $teacher->name.' đã là giáo viên của lớp.']);
        }

        $classRoom->teachers()->attach($teacher->id, ['role' => $role]);

        $teacher->notify(new ClassSpaceNotice('🧑‍🏫', 'info', 'Bạn được thêm vào lớp '.$classRoom->name,
            $actor->name.' đã thêm bạn vào lớp '.$classRoom->name.' với vai trò '.mb_strtolower(self::ROLE_LABELS[$role]).'.',
            route('teacher.classes.show', ['class' => $classRoom->id]), $actor->name));

        return $teacher;
    }

    public function setTeacherRole(ClassRoom $classRoom, int $teacherId, string $role): void
    {
        abort_unless(in_array($role, ['co_teacher', 'assistant'], true), 422);
        $current = DB::table('class_teachers')->where('class_room_id', $classRoom->id)->where('user_id', $teacherId)->value('role');
        abort_if($current === null, 404);
        if ($current === 'main') {
            throw ValidationException::withMessages(['role' => 'Không đổi vai trò của giáo viên chính.']);
        }
        DB::table('class_teachers')->where('class_room_id', $classRoom->id)->where('user_id', $teacherId)->update(['role' => $role, 'updated_at' => now()]);
    }

    public function removeTeacher(ClassRoom $classRoom, User $actor, int $teacherId): void
    {
        $current = DB::table('class_teachers')->where('class_room_id', $classRoom->id)->where('user_id', $teacherId)->value('role');
        abort_if($current === null, 404);
        if ($current === 'main') {
            throw ValidationException::withMessages(['teacher_id' => 'Không gỡ được giáo viên chính của lớp.']);
        }
        $classRoom->teachers()->detach($teacherId);

        if ($teacherId !== (int) $actor->id && ($t = User::query()->find($teacherId))) {
            $t->notify(new ClassSpaceNotice('👋', 'neutral', 'Bạn không còn dạy lớp '.$classRoom->name,
                $actor->name.' đã gỡ bạn khỏi danh sách giáo viên lớp '.$classRoom->name.'.', null, $actor->name));
        }
    }

    // ───────────────────────── Tab 3: Điểm danh ─────────────────────────

    /**
     * Sổ điểm danh: dòng = học sinh, cột = buổi học trong khoảng ngày (mỗi trang 4 buổi, mặc định trang cuối = mới nhất).
     *
     * @param  array{range?:string, from?:string, to?:string, p?:int|string}  $q
     */
    public function attendance(ClassRoom $classRoom, array $q): array
    {
        $today = now()->startOfDay();
        $range = in_array($q['range'] ?? '', ['recent', 'week', 'month', 'all', 'custom'], true) ? $q['range'] : 'recent';
        [$from, $to] = match ($range) {
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'all' => [null, null],
            'custom' => [$this->parseDate($q['from'] ?? null), $this->parseDate($q['to'] ?? null, true)],
            default => [$today->copy()->subDays(30), $today->copy()->addDays(14)->endOfDay()],
        };
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $numbering = ClassSession::query()->where('class_room_id', $classRoom->id)->orderBy('starts_at')->orderBy('id')->pluck('id')
            ->values()->flip()->map(fn ($i) => $i + 1);

        $sessions = ClassSession::query()->where('class_room_id', $classRoom->id)
            ->when($from, fn ($w) => $w->where('starts_at', '>=', $from))
            ->when($to, fn ($w) => $w->where('starts_at', '<=', $to))
            ->orderBy('starts_at')->orderBy('id')->get();

        $per = 4; // 4 buổi / trang: vừa một màn hình (mỗi buổi 2 cột Điểm danh + Nhận xét)
        $pages = max(1, (int) ceil($sessions->count() / $per));
        $page = isset($q['p']) && (int) $q['p'] >= 1 ? min((int) $q['p'], $pages) : $pages;
        $cols = $sessions->slice(($page - 1) * $per, $per)->values();

        $roster = $this->roster($classRoom);
        $records = Attendance::query()->whereIn('class_session_id', $sessions->pluck('id'))->whereIn('student_id', $roster->pluck('id'))->get()
            ->groupBy('class_session_id')->map(fn ($g) => $g->keyBy('student_id'));

        $dow = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $colData = $cols->map(fn (ClassSession $s) => [
            'id' => $s->id,
            'no' => (int) ($numbering[$s->id] ?? 0),
            'date' => $dow[$s->starts_at->dayOfWeek].' '.$s->starts_at->format('d/m'),
            'time' => $s->starts_at->format('H:i').' – '.$s->ends_at->format('H:i'),
            'topic' => (string) ($s->topic ?? ''),
            'location' => (string) ($s->location ?? ''),
            'started' => $s->starts_at->lte(now()),
            'edit' => [
                'id' => $s->id, 'topic' => (string) ($s->topic ?? ''), 'location' => (string) ($s->location ?? ''),
                'sd' => $s->starts_at->format('Y-m-d'), 'sh' => $s->starts_at->format('H'), 'sm' => sprintf('%02d', intdiv((int) $s->starts_at->format('i'), 5) * 5),
                'ed' => $s->ends_at->format('Y-m-d'), 'eh' => $s->ends_at->format('H'), 'em' => sprintf('%02d', intdiv((int) $s->ends_at->format('i'), 5) * 5),
            ],
        ])->all();

        $rows = $roster->map(function (User $u) use ($cols, $sessions, $records) {
            $cells = [];
            foreach ($cols as $s) {
                $r = $records[$s->id][$u->id] ?? null;
                $cells[$s->id] = ['s' => $r ? $this->statusValue($r->status) : null, 'n' => (string) ($r->note ?? '')];
            }
            // Tổng theo CẢ khoảng ngày (không chỉ trang đang xem); chỉ tính buổi đã có điểm danh của em.
            $marked = 0; $came = 0;
            foreach ($sessions as $s) {
                if ($r = $records[$s->id][$u->id] ?? null) {
                    $marked++;
                    $came += in_array($this->statusValue($r->status), ['present', 'late'], true) ? 1 : 0;
                }
            }

            return $this->person($u) + ['contact' => $this->maskContact($u->email, $u->phone), 'cells' => $cells, 'came' => $came, 'marked' => $marked];
        })->values()->all();

        $pending = ClassEnrollment::query()->where('class_room_id', $classRoom->id)->where('status', ClassEnrollment::STATUS_PENDING)
            ->with('student:id,name,email,phone,avatar_path')->orderBy('requested_at')->get()
            ->filter(fn ($e) => $e->student !== null)
            ->map(fn ($e) => $this->person($e->student) + [
                'enrollmentId' => $e->id,
                'contact' => $this->maskContact($e->student->email, $e->student->phone),
                'at' => $e->requested_at?->format('H:i d/m/Y'),
            ])->values()->all();

        return [
            'range' => $range,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'cols' => $colData,
            'rows' => $rows,
            'page' => $page,
            'pages' => $pages,
            'totalSessions' => $sessions->count(),
            'firstNo' => $colData[0]['no'] ?? 0,
            'lastNo' => $colData ? end($colData)['no'] : 0,
            'pending' => $pending,
        ];
    }

    private function parseDate(?string $v, bool $end = false): ?Carbon
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        try {
            $d = Carbon::createFromFormat('Y-m-d', $v);
        } catch (\Throwable $e) {
            return null;
        }

        return $end ? $d->endOfDay() : $d->startOfDay();
    }

    /** Lưu MỘT ô của sổ điểm danh (trạng thái và/hoặc nhận xét). Ô chưa điểm danh mà ghi nhận xét → mặc định "Có mặt". */
    public function saveAttendanceCell(ClassRoom $classRoom, User $actor, int $sessionId, int $studentId, ?string $status, ?string $note): array
    {
        $session = ClassSession::query()->where('class_room_id', $classRoom->id)->findOrFail($sessionId);
        abort_unless(in_array($studentId, $this->rosterIds($classRoom), true), 422, 'Học sinh không thuộc lớp.');
        if ($status !== null && ! array_key_exists($status, self::STATUS_LABELS)) {
            throw ValidationException::withMessages(['status' => 'Trạng thái điểm danh không hợp lệ.']);
        }

        $record = Attendance::query()->firstOrNew(['class_session_id' => $session->id, 'student_id' => $studentId]);
        if ($status !== null) {
            $record->status = $status;
        } elseif (! $record->exists) {
            $record->status = 'present';
        }
        if ($note !== null) {
            $record->note = trim(mb_substr($note, 0, 1000)) ?: null;
        }
        $record->source = 'manual';
        $record->recorded_by = $actor->id;
        $record->save();

        return ['s' => $this->statusValue($record->status), 'n' => (string) ($record->note ?? '')];
    }

    /** CSV sổ điểm danh trong khoảng ngày đang lọc (mọi buổi, không phân trang) — mở được bằng Excel (UTF-8 BOM). */
    public function attendanceCsv(ClassRoom $classRoom, array $q): string
    {
        $first = $this->attendance($classRoom, ['p' => 1] + $q);
        $cols = []; $rows = [];
        for ($p = 1; $p <= $first['pages']; $p++) {
            $chunk = $p === 1 ? $first : $this->attendance($classRoom, ['p' => $p] + $q);
            $cols = array_merge($cols, $chunk['cols']);
            foreach ($chunk['rows'] as $r) {
                $rows[$r['id']]['name'] = $r['name'];
                $rows[$r['id']]['cells'] = ($rows[$r['id']]['cells'] ?? []) + $r['cells'];
                $rows[$r['id']]['came'] = $r['came'];
                $rows[$r['id']]['marked'] = $r['marked'];
            }
        }

        $out = [];
        $head = ['STT', 'Học sinh'];
        foreach ($cols as $c) {
            $head[] = 'Buổi '.$c['no'].' '.$c['date'].' - Điểm danh';
            $head[] = 'Buổi '.$c['no'].' - Nhận xét';
        }
        $head[] = 'Đi học';
        $out[] = $head;
        $i = 0;
        foreach ($rows as $r) {
            $line = [++$i, $r['name']];
            foreach ($cols as $c) {
                $cell = $r['cells'][$c['id']] ?? ['s' => null, 'n' => ''];
                $line[] = $cell['s'] ? self::STATUS_LABELS[$cell['s']] : '';
                $line[] = $cell['n'];
            }
            $line[] = $r['came'].'/'.$r['marked'];
            $out[] = $line;
        }

        return $this->toCsv($out);
    }

    private function toCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($v) => (string) $v, $row));
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }

    // ───────────────────────── Tab 5: Bài tập / Đề ─────────────────────────

    /** Bổ sung số học sinh đã nộp cho danh sách bài giao (danh sách gốc do ClassRoomService::showForTeacher dựng). */
    public function assignmentStats(ClassRoom $classRoom): array
    {
        $rosterIds = $this->rosterIds($classRoom);
        $submitted = Attempt::query()->where('class_room_id', $classRoom->id)->whereNotNull('assignment_id')
            ->whereIn('user_id', $rosterIds ?: [0])->whereNotNull('submitted_at')
            ->select('assignment_id', DB::raw('count(distinct user_id) as n'))->groupBy('assignment_id')->pluck('n', 'assignment_id');
        $grading = Attempt::query()->where('class_room_id', $classRoom->id)->whereNotNull('assignment_id')->where('status', 'grading')
            ->select('assignment_id', DB::raw('count(*) as n'))->groupBy('assignment_id')->pluck('n', 'assignment_id');
        $types = Assignment::query()->where('class_room_id', $classRoom->id)->with('assessment:id,type')->get(['id', 'assessment_id'])
            ->mapWithKeys(fn ($a) => [$a->id => $this->statusValue($a->assessment?->type)]);

        return [
            'roster' => count($rosterIds),
            'submitted' => $submitted->map(fn ($v) => (int) $v)->all(),
            'grading' => $grading->map(fn ($v) => (int) $v)->all(),
            'types' => $types->all(),
        ];
    }

    /** Nhắc các em CHƯA NỘP một bài giao (chuông thông báo). Mỗi bài nhắc tối đa 1 lần / 10 phút. Trả về số em được nhắc. */
    public function remindAssignment(ClassRoom $classRoom, User $actor, int $assignmentId): int
    {
        $assignment = Assignment::query()->where('class_room_id', $classRoom->id)->with('assessment:id,title')->findOrFail($assignmentId);
        $key = 'class-remind:'.$assignment->id;
        if (Cache::has($key)) {
            throw ValidationException::withMessages(['assignment' => 'Vừa nhắc bài này rồi — đợi ít phút rồi nhắc lại nhé.']);
        }

        $done = Attempt::query()->where('assignment_id', $assignment->id)->whereNotNull('submitted_at')->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        $targets = array_values(array_diff($this->rosterIds($classRoom), $done));
        if ($targets === []) {
            return 0;
        }

        $title = (string) ($assignment->assessment->title ?? 'bài được giao');
        $due = $assignment->closes_at ? ' Hạn nộp: '.$assignment->closes_at->format('H:i d/m/Y').'.' : '';
        Notification::send(User::query()->whereIn('id', $targets)->get(), new ClassSpaceNotice('⏰', 'warning', 'Nhắc nộp bài: '.$title,
            'Lớp '.$classRoom->name.': em chưa nộp "'.$title.'".'.$due, route('student.assignments.index'), 'Giáo viên '.$actor->name));
        Cache::put($key, 1, now()->addMinutes(10));

        return count($targets);
    }

    // ───────────────────────── Tab 6: Tiến trình dạy học ─────────────────────────

    /** Bước có hẹn ngày mở mà đã tới ngày → mở khoá (gọi khi giáo viên / học sinh mở trang lớp). */
    public function syncScheduledUnlocks(ClassRoom $classRoom): void
    {
        if (! ClassProgressStep::isReady()) {
            return;
        }
        $due = ClassProgressStep::query()->where('class_room_id', $classRoom->id)->where('is_unlocked', false)
            ->whereNotNull('unlock_on')->whereDate('unlock_on', '<=', now()->toDateString())->get();
        foreach ($due as $step) {
            $step->forceFill(['is_unlocked' => true, 'unlocked_at' => now()])->save();
            $this->notifyStepUnlocked($classRoom, $step);
        }
    }

    public function progress(ClassRoom $classRoom): array
    {
        if (! ClassProgressStep::isReady()) {
            return ['ready' => false, 'steps' => [], 'materials' => [], 'assignments' => [], 'open' => 0, 'total' => 0];
        }

        $materials = ClassMaterial::query()->where('class_room_id', $classRoom->id)->where('status', 'active')->with('product:id,title,type')->get()
            ->mapWithKeys(fn ($cm) => [$cm->id => ['id' => $cm->id, 'title' => (string) ($cm->product->title ?? 'Tài liệu'), 'kind' => $this->statusValue($cm->product?->type)]]);
        $assignments = Assignment::query()->where('class_room_id', $classRoom->id)->where('status', '!=', AssignmentStatus::Archived->value)
            ->with('assessment:id,title')->orderBy('created_at')->get()
            ->mapWithKeys(fn ($a) => [$a->id => ['id' => $a->id, 'title' => (string) ($a->assessment->title ?? 'Bài được giao')]]);

        $rosterIds = $this->rosterIds($classRoom);
        $submittedBy = Attempt::query()->where('class_room_id', $classRoom->id)->whereIn('assignment_id', $assignments->keys()->all() ?: [0])
            ->whereIn('user_id', $rosterIds ?: [0])->whereNotNull('submitted_at')->get(['assignment_id', 'user_id'])
            ->groupBy('assignment_id')->map(fn ($g) => $g->pluck('user_id')->map(fn ($v) => (int) $v)->unique()->all());

        $steps = ClassProgressStep::query()->where('class_room_id', $classRoom->id)->orderBy('position')->orderBy('id')->get()
            ->map(function (ClassProgressStep $s) use ($materials, $assignments, $submittedBy, $rosterIds) {
                $items = [];
                $aIds = [];
                foreach ((array) $s->items as $it) {
                    $type = $it['type'] ?? '';
                    $id = (int) ($it['id'] ?? 0);
                    if ($type === 'material' && isset($materials[$id])) {
                        $items[] = ['type' => 'material', 'id' => $id, 'title' => $materials[$id]['title'], 'kind' => $materials[$id]['kind']];
                    } elseif ($type === 'assignment' && isset($assignments[$id])) {
                        $items[] = ['type' => 'assignment', 'id' => $id, 'title' => $assignments[$id]['title']];
                        $aIds[] = $id;
                    }
                }
                // Hoàn thành = em đã nộp ĐỦ mọi bài tập / đề của bước (bước không có bài thì không tính %).
                $done = null;
                if ($aIds !== [] && $rosterIds !== []) {
                    $sets = array_map(fn ($a) => $submittedBy[$a] ?? [], $aIds);
                    $done = count(array_intersect($rosterIds, ...$sets));
                }

                return [
                    'id' => $s->id,
                    'position' => $s->position,
                    'title' => $s->title,
                    'description' => (string) $s->description,
                    'unlocked' => (bool) $s->is_unlocked,
                    'unlockedAt' => $s->unlocked_at?->format('d/m/Y'),
                    'unlockOn' => $s->unlock_on?->format('Y-m-d'),
                    'unlockOnLabel' => $s->unlock_on?->format('d/m/Y'),
                    'items' => $items,
                    'done' => $done,
                    'roster' => count($rosterIds),
                    'pct' => $done !== null ? (int) round($done / max(1, count($rosterIds)) * 100) : null,
                ];
            })->values();

        // Bước "đang học" = bước đã mở có vị trí lớn nhất.
        $currentId = ($steps->where('unlocked', true)->sortByDesc('position')->first())['id'] ?? null;

        return [
            'ready' => true,
            'steps' => $steps->map(fn ($s) => $s + ['current' => $s['id'] === $currentId])->all(),
            'materials' => $materials->values()->all(),
            'assignments' => $assignments->values()->all(),
            'open' => $steps->where('unlocked', true)->count(),
            'total' => $steps->count(),
        ];
    }

    /** @param array{title:string, description?:?string, unlock_on?:?string, material_ids?:array, assignment_ids?:array} $data */
    public function saveStep(ClassRoom $classRoom, User $actor, array $data, ?int $stepId = null): ClassProgressStep
    {
        abort_unless(ClassProgressStep::isReady(), 503, 'Chưa cập nhật cơ sở dữ liệu cho tiến trình dạy học — chạy php artisan migrate.');

        $mIds = ClassMaterial::query()->where('class_room_id', $classRoom->id)->whereIn('id', array_map('intval', $data['material_ids'] ?? []))->pluck('id')->all();
        $aIds = Assignment::query()->where('class_room_id', $classRoom->id)->whereIn('id', array_map('intval', $data['assignment_ids'] ?? []))->pluck('id')->all();
        $items = array_merge(
            array_map(fn ($id) => ['type' => 'material', 'id' => (int) $id], $mIds),
            array_map(fn ($id) => ['type' => 'assignment', 'id' => (int) $id], $aIds),
        );

        $attrs = [
            'title' => trim($data['title']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'unlock_on' => ! empty($data['unlock_on']) ? $data['unlock_on'] : null,
            'items' => $items,
        ];

        if ($stepId !== null) {
            $step = ClassProgressStep::query()->where('class_room_id', $classRoom->id)->findOrFail($stepId);
            $step->fill($attrs)->save();
        } else {
            $step = ClassProgressStep::query()->create($attrs + [
                'class_room_id' => $classRoom->id,
                'position' => (int) ClassProgressStep::query()->where('class_room_id', $classRoom->id)->max('position') + 1,
                'created_by' => $actor->id,
            ]);
        }
        $this->syncScheduledUnlocks($classRoom);

        return $step;
    }

    public function deleteStep(ClassRoom $classRoom, int $stepId): void
    {
        ClassProgressStep::query()->where('class_room_id', $classRoom->id)->findOrFail($stepId)->delete();
        $this->renumberSteps($classRoom);
    }

    /** Bật / tắt mở khoá. Mở khoá thì báo học sinh (nếu $notify). */
    public function toggleStep(ClassRoom $classRoom, int $stepId, bool $notify = true): bool
    {
        $step = ClassProgressStep::query()->where('class_room_id', $classRoom->id)->findOrFail($stepId);
        $open = ! $step->is_unlocked;
        $step->forceFill([
            'is_unlocked' => $open,
            'unlocked_at' => $open ? now() : null,
            // Khoá lại bằng tay thì bỏ hẹn ngày đã qua, kẻo lần tải trang sau lại tự mở.
            'unlock_on' => (! $open && $step->unlock_on && $step->unlock_on->lte(now())) ? null : $step->unlock_on,
        ])->save();
        if ($open && $notify) {
            $this->notifyStepUnlocked($classRoom, $step);
        }

        return $open;
    }

    public function moveStep(ClassRoom $classRoom, int $stepId, string $dir): void
    {
        $this->renumberSteps($classRoom);
        $steps = ClassProgressStep::query()->where('class_room_id', $classRoom->id)->orderBy('position')->get()->values();
        $i = $steps->search(fn ($s) => (int) $s->id === $stepId);
        abort_if($i === false, 404);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= $steps->count()) {
            return;
        }
        [$a, $b] = [$steps[$i], $steps[$j]];
        DB::transaction(function () use ($a, $b) {
            $pa = $a->position;
            $a->forceFill(['position' => $b->position])->save();
            $b->forceFill(['position' => $pa])->save();
        });
    }

    private function renumberSteps(ClassRoom $classRoom): void
    {
        $n = 0;
        foreach (ClassProgressStep::query()->where('class_room_id', $classRoom->id)->orderBy('position')->orderBy('id')->get() as $s) {
            if ($s->position !== ++$n) {
                $s->forceFill(['position' => $n])->save();
            }
        }
    }

    private function notifyStepUnlocked(ClassRoom $classRoom, ClassProgressStep $step): void
    {
        $ids = $this->rosterIds($classRoom);
        if ($ids === []) {
            return;
        }
        Notification::send(User::query()->whereIn('id', $ids)->get(), new ClassSpaceNotice('🔓', 'success',
            'Mở bài mới: '.$step->title, 'Lớp '.$classRoom->name.' vừa mở bước "'.$step->title.'" — vào học ngay nhé!',
            route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'progress'])));
    }

    /** Các bước ĐÃ MỞ cho trang lớp của học sinh (kèm link tài liệu / bài tập). */
    public function stepsForStudent(ClassRoom $classRoom): array
    {
        if (! ClassProgressStep::isReady()) {
            return [];
        }
        $this->syncScheduledUnlocks($classRoom);
        $data = $this->progress($classRoom);

        return collect($data['steps'])->where('unlocked', true)->values()->all();
    }

    // ───────────────────────── Tab 7: Kết quả học tập ─────────────────────────

    /** Bảng điểm thang 10: cột = bài giao (đã phát hành), ô = điểm giáo viên chấm đè ?? điểm tự động của lần nộp mới nhất. */
    public function results(ClassRoom $classRoom): array
    {
        $roster = $this->roster($classRoom);
        $rosterIds = $roster->pluck('id')->map(fn ($v) => (int) $v)->all();

        $assignments = Assignment::query()->where('class_room_id', $classRoom->id)
            ->whereNotIn('status', [AssignmentStatus::Draft->value, AssignmentStatus::Archived->value])
            ->with('assessment:id,title,total_points')->orderBy('created_at')->orderBy('id')->get();

        $attempts = Attempt::query()->whereIn('assignment_id', $assignments->pluck('id')->all() ?: [0])
            ->whereIn('user_id', $rosterIds ?: [0])->whereNotNull('submitted_at')
            ->orderBy('submitted_at')->get(['id', 'assignment_id', 'user_id', 'total_score', 'status', 'submitted_at'])
            ->keyBy(fn ($a) => $a->assignment_id.':'.$a->user_id);

        $grades = ClassGrade::isReady()
            ? ClassGrade::query()->where('class_room_id', $classRoom->id)->get()->keyBy(fn ($g) => ($g->assignment_id ?? 0).':'.$g->student_id)
            : collect();

        $sessionIds = ClassSession::query()->where('class_room_id', $classRoom->id)->pluck('id');
        $att = Attendance::query()->whereIn('class_session_id', $sessionIds)->whereIn('student_id', $rosterIds ?: [0])->get(['student_id', 'status'])
            ->groupBy('student_id');

        $cols = $assignments->map(fn (Assignment $a) => [
            'id' => $a->id,
            'title' => (string) ($a->assessment->title ?? 'Bài được giao'),
            'max' => (float) ($a->assessment->total_points ?? 0),
            'closed' => $a->closes_at !== null && $a->closes_at->isPast(),
        ])->values()->all();

        $rows = [];
        $colSums = array_fill_keys(array_column($cols, 'id'), [0, 0]);
        foreach ($roster as $u) {
            $cells = [];
            $vals = [];
            $missingClosed = 0;
            foreach ($cols as $c) {
                $at = $attempts[$c['id'].':'.$u->id] ?? null;
                $g = $grades[$c['id'].':'.$u->id] ?? null;
                $auto = null;
                if ($at && $at->total_score !== null && $this->statusValue($at->status) !== 'grading') {
                    $auto = $c['max'] > 0 ? round((float) $at->total_score / $c['max'] * 10, 1) : round((float) $at->total_score, 1);
                    $auto = max(0, min(10, $auto));
                }
                $score = $g?->score !== null ? round((float) $g->score, 1) : $auto;
                $cells[$c['id']] = [
                    'score' => $score,
                    'auto' => $auto,
                    'override' => $g?->score !== null,
                    'pending' => $at !== null && $score === null,
                    'submitted' => $at !== null,
                    'comment' => (string) ($g->comment ?? ''),
                    'attemptId' => $at?->id,
                ];
                if ($score !== null) {
                    $vals[] = $score;
                    $colSums[$c['id']][0] += $score;
                    $colSums[$c['id']][1]++;
                }
                if ($at === null && $c['closed']) {
                    $missingClosed++;
                }
            }
            $avg = $vals ? round(array_sum($vals) / count($vals), 1) : null;
            $marks = $att[$u->id] ?? collect();
            $came = $marks->filter(fn ($a) => in_array($this->statusValue($a->status), ['present', 'late'], true))->count();
            $rows[] = $this->person($u) + [
                'cells' => $cells,
                'avg' => $avg,
                'rank' => $this->rankOf($avg),
                'attRate' => $marks->count() ? (int) round($came / $marks->count() * 100) : null,
                'note' => (string) ($grades['0:'.$u->id]->comment ?? ''),
                'missingClosed' => $missingClosed,
            ];
        }

        $colAvg = collect($colSums)->map(fn ($s) => $s[1] ? round($s[0] / $s[1], 1) : null)->all();
        $avgs = array_filter(array_column($rows, 'avg'), fn ($v) => $v !== null);
        $rates = array_filter(array_column($rows, 'attRate'), fn ($v) => $v !== null);

        return [
            'cols' => $cols,
            'rows' => $rows,
            'colAvg' => $colAvg,
            'classAvg' => $avgs ? round(array_sum($avgs) / count($avgs), 1) : null,
            'classAtt' => $rates ? (int) round(array_sum($rates) / count($rates)) : null,
            'ready' => ClassGrade::isReady(),
        ];
    }

    public function rankOf(?float $avg): ?array
    {
        return match (true) {
            $avg === null => null,
            $avg >= 8 => ['Giỏi', 'ok'],
            $avg >= 6.5 => ['Khá', 'late'],
            $avg >= 5 => ['Trung bình', 'ex'],
            default => ['Yếu', 'no'],
        };
    }

    /** Lưu điểm chấm đè / nhận xét. assignmentId null = nhận xét chung. score null + comment rỗng = xoá (trả về điểm tự động). */
    public function saveGrade(ClassRoom $classRoom, User $actor, int $studentId, ?int $assignmentId, ?float $score, ?string $comment): array
    {
        abort_unless(ClassGrade::isReady(), 503, 'Chưa cập nhật cơ sở dữ liệu cho bảng điểm — chạy php artisan migrate.');
        abort_unless(in_array($studentId, $this->rosterIds($classRoom), true), 422, 'Học sinh không thuộc lớp.');
        if ($assignmentId !== null) {
            abort_unless(Assignment::query()->where('class_room_id', $classRoom->id)->whereKey($assignmentId)->exists(), 404);
        }
        if ($score !== null && ($score < 0 || $score > 10)) {
            throw ValidationException::withMessages(['score' => 'Điểm phải từ 0 đến 10.']);
        }
        $comment = trim(mb_substr((string) $comment, 0, 2000));

        $where = ['class_room_id' => $classRoom->id, 'student_id' => $studentId, 'assignment_id' => $assignmentId];
        if ($score === null && $comment === '') {
            ClassGrade::query()->where($where)->delete();

            return ['score' => null, 'comment' => ''];
        }
        ClassGrade::query()->updateOrCreate($where, ['score' => $score, 'comment' => $comment ?: null, 'graded_by' => $actor->id]);

        return ['score' => $score, 'comment' => $comment];
    }

    public function resultsCsv(ClassRoom $classRoom): string
    {
        $r = $this->results($classRoom);
        $out = [array_merge(['STT', 'Học sinh'], array_column($r['cols'], 'title'), ['Điểm TB', 'Đi học (%)', 'Xếp loại', 'Nhận xét'])];
        foreach ($r['rows'] as $i => $row) {
            $line = [$i + 1, $row['name']];
            foreach ($r['cols'] as $c) {
                $cell = $row['cells'][$c['id']];
                $line[] = $cell['score'] !== null ? number_format($cell['score'], 1) : ($cell['pending'] ? 'Chờ chấm' : '');
            }
            $line[] = $row['avg'] !== null ? number_format($row['avg'], 1) : '';
            $line[] = $row['attRate'] ?? '';
            $line[] = $row['rank'][0] ?? '';
            $line[] = $row['note'];
            $out[] = $line;
        }

        return $this->toCsv($out);
    }

    /** Dữ liệu phiếu kết quả (1 em hoặc cả lớp) cho trang in. */
    public function reportCards(ClassRoom $classRoom, ?int $studentId = null): array
    {
        $r = $this->results($classRoom);
        $rows = collect($r['rows'])->when($studentId !== null, fn ($c) => $c->where('id', $studentId))->values();
        abort_if($studentId !== null && $rows->isEmpty(), 404);

        $sessionIds = ClassSession::query()->where('class_room_id', $classRoom->id)->pluck('id');
        $counts = Attendance::query()->whereIn('class_session_id', $sessionIds)->whereIn('student_id', $rows->pluck('id')->all() ?: [0])
            ->get(['student_id', 'status'])->groupBy('student_id')
            ->map(fn ($g) => collect(self::STATUS_LABELS)->mapWithKeys(fn ($l, $k) => [$k => $g->filter(fn ($a) => $this->statusValue($a->status) === $k)->count()])->all());

        $teachers = $classRoom->teachers()->get(['users.id', 'users.name'])
            ->sortBy(fn ($t) => $t->pivot->role === 'main' ? 0 : 1)->map(fn ($t) => $t->name.' ('.(self::ROLE_LABELS[$t->pivot->role] ?? 'Giáo viên').')')->values()->all();

        return [
            'classRoom' => $classRoom,
            'teachers' => $teachers,
            'cols' => $r['cols'],
            'cards' => $rows->map(fn ($row) => $row + ['att' => $counts[$row['id']] ?? array_fill_keys(array_keys(self::STATUS_LABELS), 0)])->all(),
            'statusLabels' => self::STATUS_LABELS,
            'printedAt' => now()->format('d/m/Y'),
        ];
    }
}
