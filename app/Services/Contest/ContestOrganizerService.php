<?php

namespace App\Services\Contest;

use App\Models\ClassEnrollment;
use App\Models\ClassRoom;
use App\Models\Competition;
use App\Models\CompetitionRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\CompetitionService as AdminCompetitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 24/10 — tab "Học sinh" của BAN TỔ CHỨC trong phòng thi mới (source: ContestParticipants.jsx + contestAccess.js).
 *
 * Ai quản lý được một cuộc thi: quản trị viên, hoặc giáo viên nằm trong danh sách "Giáo viên tổ chức / cố vấn"
 * của cuộc thi (bảng competition_advisors). Giáo viên chỉ thêm được học sinh đang học (active) ở lớp mình dạy;
 * quản trị viên thêm được mọi tài khoản học sinh đang hoạt động.
 *
 * Duyệt / từ chối đơn dùng lại đúng Admin\CompetitionService (có gửi thông báo cho học sinh) — không viết lại luật.
 */
class ContestOrganizerService
{
    /** Số học sinh tối đa trả về cho một lần tìm ở hộp thoại "Thêm học sinh". */
    private const SEARCH_LIMIT = 40;

    public function __construct(private readonly AdminCompetitionService $adminCompetitions) {}

    public function canManage(?User $user, Competition $competition): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        if (! $user->hasRole(Role::TEACHER)) {
            return false;
        }

        return $competition->relationLoaded('advisors')
            ? $competition->advisors->contains('id', $user->id)
            : $competition->advisors()->where('users.id', $user->id)->exists();
    }

    public function isAdmin(User $user): bool
    {
        return $user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN);
    }

    /** @return array<int, array{id:int, name:string, code:string}> lớp hiện trong ô lọc "Lớp học". */
    public function classOptions(User $user): array
    {
        $query = ClassRoom::query()->orderBy('name');

        if (! $this->isAdmin($user)) {
            $query->whereHas('teachers', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query->limit(300)->get(['id', 'name', 'code'])
            ->map(fn (ClassRoom $c) => ['id' => (int) $c->id, 'name' => (string) $c->name, 'code' => (string) ($c->code ?? '')])
            ->all();
    }

    /**
     * Tìm học sinh để thêm vào cuộc thi (JSON cho hộp thoại).
     *
     * @return array<int, array{id:int, name:string, account:string, className:string, added:bool}>
     */
    public function searchStudents(User $user, Competition $competition, string $q, ?int $classId): array
    {
        $this->assertCanManage($user, $competition);

        $query = $this->studentScope($user, $classId);

        $q = trim($q);
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
        }

        $students = $query->orderBy('name')->limit(self::SEARCH_LIMIT)->get(['id', 'name', 'email', 'phone']);
        $ids = $students->pluck('id')->map(fn ($id) => (int) $id)->all();

        $approved = CompetitionRegistration::supported() && $ids !== []
            ? CompetitionRegistration::query()
                ->where('competition_id', $competition->id)
                ->whereIn('student_id', $ids)
                ->where('status', CompetitionRegistration::STATUS_APPROVED)
                ->pluck('student_id')->map(fn ($id) => (int) $id)->all()
            : [];

        $classNames = $this->classNamesFor($ids);

        return $students->map(fn (User $s) => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'account' => (string) ($s->email ?: $s->phone ?: ''),
            'className' => $classNames[(int) $s->id] ?? '',
            'added' => in_array((int) $s->id, $approved, true),
        ])->values()->all();
    }

    /**
     * Thêm học sinh vào cuộc thi = duyệt tham gia ngay (khảo sát: vào danh sách theo dõi, vẫn phải nhập mật khẩu).
     *
     * @param  array<int, int|string>  $studentIds
     * @return array{added:int, approved:int, skipped:int}
     */
    public function addStudents(User $user, Competition $competition, array $studentIds, bool $eventClosed): array
    {
        $this->assertCanManage($user, $competition);

        if (! CompetitionRegistration::supported()) {
            throw ValidationException::withMessages(['students' => 'Máy chủ chưa chạy migration — chạy "php artisan migrate" rồi thử lại.']);
        }

        if ($eventClosed) {
            throw ValidationException::withMessages(['students' => 'Sự kiện đã kết thúc, không thể thêm học sinh.']);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            throw ValidationException::withMessages(['students' => 'Chọn ít nhất một học sinh.']);
        }

        // Chỉ nhận học sinh trong phạm vi được quản lý (chặn gửi tay id ngoài phạm vi).
        $allowed = $this->studentScope($user, null)->whereIn('users.id', $ids)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (count($allowed) !== count($ids)) {
            throw ValidationException::withMessages(['students' => 'Có học sinh không còn hoạt động hoặc không thuộc lớp bạn quản lý. Hãy mở lại danh sách.']);
        }

        $existing = CompetitionRegistration::query()
            ->where('competition_id', $competition->id)
            ->whereIn('student_id', $ids)
            ->get()->keyBy('student_id');

        $added = 0;
        $approved = 0;
        $now = now();
        $hub = CompetitionRegistration::supportsHubFields();

        foreach ($ids as $id) {
            $row = $existing->get($id);
            if ($row !== null && $row->isApproved()) {
                continue;
            }

            $values = [
                'status' => CompetitionRegistration::STATUS_APPROVED,
                'requested_at' => $row?->requested_at ?? $now,
                'approved_at' => $now,
                'approved_by' => $user->id,
                'reject_reason' => null,
            ];
            if ($hub) {
                $values['source'] = CompetitionRegistration::SOURCE_ORGANIZER;
                $values['added_by'] = $user->id;
            }

            CompetitionRegistration::updateOrCreate(['competition_id' => $competition->id, 'student_id' => $id], $values);
            $row === null ? $added++ : $approved++;
        }

        return ['added' => $added, 'approved' => $approved, 'skipped' => count($ids) - $added - $approved];
    }

    public function review(User $user, Competition $competition, int $registrationId, bool $approve, ?string $reason = null): void
    {
        $this->assertCanManage($user, $competition);

        $approve
            ? $this->adminCompetitions->approveRegistration($user, $competition->id, $registrationId)
            : $this->adminCompetitions->rejectRegistration($user, $competition->id, $registrationId, $reason);
    }

    /** id học sinh → tên lớp đang học (lớp đầu tiên tìm thấy). @return array<int, string> */
    public function classNamesFor(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $names = [];
        ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->with('classRoom:id,name')
            ->orderBy('id')
            ->get(['id', 'student_id', 'class_room_id'])
            ->each(function (ClassEnrollment $e) use (&$names) {
                $names[(int) $e->student_id] ??= (string) ($e->classRoom->name ?? '');
            });

        return $names;
    }

    private function assertCanManage(User $user, Competition $competition): void
    {
        abort_unless($this->canManage($user, $competition), 403, 'Bạn không có quyền quản lý cuộc thi này.');
    }

    /** Tài khoản học sinh đang hoạt động trong phạm vi người quản lý. */
    private function studentScope(User $user, ?int $classId): Builder
    {
        $query = User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', Role::STUDENT))
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active'));

        $classIds = null;
        if (! $this->isAdmin($user)) {
            $classIds = ClassRoom::query()->whereHas('teachers', fn ($q) => $q->where('users.id', $user->id))->pluck('id')->all();
        }
        if ($classId !== null) {
            $classIds = $classIds === null ? [$classId] : array_values(array_intersect($classIds, [$classId]));
        }

        if ($classIds !== null) {
            $query->whereIn('users.id', ClassEnrollment::query()
                ->select('student_id')
                ->whereIn('class_room_id', $classIds === [] ? [0] : $classIds)
                ->where('status', ClassEnrollment::STATUS_ACTIVE));
        }

        return $query;
    }
}
