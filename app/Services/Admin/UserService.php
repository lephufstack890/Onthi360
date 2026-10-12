<?php

namespace App\Services\Admin;

use App\Enums\ParentLinkStatus;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\ParentLink;
use App\Models\Role;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.users.* (ADM-02, 10.4 + 3.1/3.2).
 *
 * Lưu ý audit: App\Models\User CHỦ Ý KHÔNG gắn trait App\Concerns\Auditable (khác với các
 * model khác trong hệ thống) — User là model Authenticatable, "updated" xảy ra ở MỌI lượt
 * đăng nhập (remember_token đổi), MỌI người dùng tự sửa hồ sơ, không riêng hành động của
 * admin — gắn trait sẽ ghi log tràn lan và có thể lộ giá trị password/remember_token đã đổi
 * vào cột "changes". Thay vào đó, service này tự ghi AuditLog thủ công, CHỈ cho các trường
 * và hành động admin thao tác ở đây (16 mục 4: audit "role" + các thay đổi admin thực hiện).
 */
class UserService
{
    private const ROLE_LABELS = [
        Role::STUDENT => 'Học sinh', Role::TEACHER => 'Giáo viên', Role::PARENT => 'Phụ huynh',
        Role::EDITOR => 'Editor', Role::ADMIN => 'Admin', Role::SUPER_ADMIN => 'Super Admin',
    ];

    public function __construct(
        private UserRepositoryInterface $users,
        private AuditLogRepositoryInterface $auditLogs,
        private ParentLinkRepositoryInterface $parentLinks,
        private UserPermissionService $permissionService,
    ) {}

    private function logChange(User $admin, User $target, string $action, array $changes, ?string $reason = null): void
    {
        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'changes' => $changes,
            'reason' => $reason,
        ]);
    }

    /** admin.users.create — dữ liệu tĩnh cho form (vai trò được phép gán qua đây). */
    public function createFormData(): array
    {
        return ['availableRoles' => self::ROLE_LABELS];
    }

    /**
     * admin.users.store — Admin/Super Admin tạo tài khoản trực tiếp (KHÁC với tự đăng ký ở
     * AuthService::register(), nơi chỉ 3 vai trò công khai được chọn) — ở đây admin được chọn
     * BẤT KỲ vai trò nào, kể cả admin/editor/super_admin, vì đây là khu quản trị đã có
     * middleware role:admin,super_admin bảo vệ.
     * Nếu gán vai trò "teacher", tự tạo TeacherProfile ở trạng thái "Chờ duyệt" — giống hệt
     * luồng tự đăng ký (3.3) — để không tạo một lối tắt bỏ qua bước duyệt giáo viên; admin có
     * thể duyệt ngay sau đó ở hàng đợi admin.teacher-approvals nếu muốn.
     */
    public function store(User $admin, array $data): User
    {
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null,
            'region' => $data['region'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        $roleNames = $data['roles'] ?? [];
        if (! empty($roleNames)) {
            $roleIds = Role::whereIn('name', $roleNames)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        if (in_array(Role::TEACHER, $roleNames, true)) {
            \App\Models\TeacherProfile::create([
                'user_id' => $user->id,
                'approval_status' => \App\Enums\TeacherApprovalStatus::Pending,
                'subjects' => [],
            ]);
        }

        $this->logChange($admin, $user, 'created', ['roles' => $roleNames]);

        return $user;
    }

    /** @return array{tab: string, tabs: array, users: array, total: int} */
    public function indexData(string $tab): array
    {
        $roleMap = [
            'student' => Role::STUDENT,
            'teacher' => Role::TEACHER,
            'parent' => Role::PARENT,
        ];

        $counts = [
            'all' => $this->users->count(),
            'student' => $this->users->countByRoleName(Role::STUDENT),
            'teacher' => $this->users->countByRoleName(Role::TEACHER),
            'parent' => $this->users->countByRoleName(Role::PARENT),
            'staff' => $this->users->countByRoleNames([Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN]),
            'deleted' => User::onlyTrashed()->count(),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.users.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Học sinh', 'href' => route('admin.users.index', ['tab' => 'student']), 'active' => $tab === 'student', 'count' => $counts['student']],
            ['label' => 'Giáo viên', 'href' => route('admin.users.index', ['tab' => 'teacher']), 'active' => $tab === 'teacher', 'count' => $counts['teacher']],
            ['label' => 'Phụ huynh', 'href' => route('admin.users.index', ['tab' => 'parent']), 'active' => $tab === 'parent', 'count' => $counts['parent']],
            ['label' => 'Admin/Editor', 'href' => route('admin.users.index', ['tab' => 'staff']), 'active' => $tab === 'staff', 'count' => $counts['staff']],
            ['label' => 'Đã xóa', 'href' => route('admin.users.index', ['tab' => 'deleted']), 'active' => $tab === 'deleted', 'count' => $counts['deleted']],
        ];

        $query = $this->users->withRolesAndTeacherProfile();
        if ($tab === 'deleted') {
            $query->onlyTrashed();
        } elseif ($tab === 'staff') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN]));
        } elseif (isset($roleMap[$tab])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleMap[$tab]));
        }

        $total = (clone $query)->count();
        // SỬA 8/10: nâng trần 50 -> 500 để giao diện tìm/lọc/chia trang 10 người/trang chạy trên đủ danh sách (logic lọc/sắp xếp không đổi).
        $users = $query->latest()->limit(500)->get()->map(fn ($u) => $this->presentUser($u))->all();

        return ['tab' => $tab, 'tabs' => $tabs, 'users' => $users, 'total' => $total];
    }

    /**
     * @return array{userModel: User, availableRoles: array, auditLogs: \Illuminate\Support\Collection,
     *     roleNames: array, studentEnrollments: \Illuminate\Support\Collection,
     *     linkedParents: \Illuminate\Support\Collection, linkedChildren: \Illuminate\Support\Collection}
     */
    public function showData(int $userId): array
    {
        $userModel = $this->users->withRolesAndTeacherProfile()->findOrFail($userId);
        $roleNames = $userModel->roles->pluck('name')->all();

        $auditLogs = $this->auditLogs->forAuditable(User::class, $userModel->id, 20);

        // Hồ sơ theo vai trò (yêu cầu: admin xem được hồ sơ giáo viên/học sinh/phụ huynh).
        // Một user có thể có NHIỀU vai trò cùng lúc (4.3) nên không dùng if/else loại trừ nhau
        // — mỗi khối dữ liệu chỉ rỗng nếu user không có vai trò tương ứng.
        $studentEnrollments = in_array(Role::STUDENT, $roleNames, true)
            ? ClassEnrollment::where('student_id', $userModel->id)->with('classRoom.course')->latest()->get()
            : collect();

        $linkedParents = in_array(Role::STUDENT, $roleNames, true)
            ? ParentLink::where('student_user_id', $userModel->id)->with('parent')->get()
            : collect();

        $linkedChildren = in_array(Role::PARENT, $roleNames, true)
            ? ParentLink::where('parent_user_id', $userModel->id)->with('student')->get()
            : collect();

        return [
            'userModel' => $userModel,
            'availableRoles' => self::ROLE_LABELS,
            'roleNames' => $roleNames,
            'auditLogs' => $auditLogs,
            'studentEnrollments' => $studentEnrollments,
            'linkedParents' => $linkedParents,
            'linkedChildren' => $linkedChildren,
        ];
    }

    /** admin.users.edit — dữ liệu form sửa thông tin cơ bản. */
    public function editFormData(int $userId): array
    {
        return [
            'userModel' => $this->users->findOrFail($userId),
        ];
    }

    /**
     * admin.users.update — sửa thông tin cơ bản (tên/email/SĐT/trạng thái). Chuyển trạng thái
     * sang "suspended" PHẢI có lý do (10.4, giống tinh thần TeacherApprovalService::suspend())
     * — validate ở Controller trước khi gọi hàm này với $reason khác null.
     */
    public function update(User $admin, User $user, array $data, ?string $reason = null): User
    {
        $fields = ['name', 'email', 'phone', 'province', 'region', 'status'];
        $before = $user->only($fields);

        $this->users->update($user, [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null,
            'region' => $data['region'] ?? null,
            'status' => $data['status'],
        ]);

        $after = $user->only($fields);
        $changed = array_diff_assoc($after, $before);
        if (! empty($changed)) {
            // Đổi trạng thái -> action riêng để tab Lịch sử hiện "Khóa/Mở khóa tài khoản" cho dễ đọc.
            $action = 'updated';
            if (array_key_exists('status', $changed)) {
                $action = $changed['status'] === 'suspended' ? 'locked' : 'unlocked';
            }
            $this->logChange($admin, $user, $action, ['before' => array_intersect_key($before, $changed), 'after' => $changed], $reason);
        }

        return $user;
    }

    /**
     * admin.users.destroy — XÓA MỀM (users.deleted_at): tài khoản không đăng nhập được nữa nhưng dữ liệu học tập,
     * đơn hàng, đánh giá... vẫn giữ nguyên và khôi phục được ở tab "Đã xóa". Bắt buộc có lý do (ghi audit).
     * Chặn: tự xóa mình; xóa Super Admin trừ khi người thao tác cũng là Super Admin; xóa quản trị viên cuối cùng.
     */
    public function delete(User $admin, User $target, string $reason): void
    {
        if ($admin->id === $target->id) {
            throw ValidationException::withMessages(['delete' => 'Không thể tự xóa tài khoản của chính mình.']);
        }

        $targetRoles = $target->roles->pluck('name')->all();
        if (in_array(Role::SUPER_ADMIN, $targetRoles, true) && ! $admin->hasRole(Role::SUPER_ADMIN)) {
            throw ValidationException::withMessages(['delete' => 'Chỉ Super Admin mới được xóa tài khoản Super Admin.']);
        }

        if (count(array_intersect($targetRoles, [Role::ADMIN, Role::SUPER_ADMIN])) > 0) {
            $remaining = $this->users->query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::ADMIN, Role::SUPER_ADMIN]))
                ->where('id', '!=', $target->id)
                ->count();
            if ($remaining === 0) {
                throw ValidationException::withMessages(['delete' => 'Không thể xóa quản trị viên cuối cùng của hệ thống.']);
            }
        }

        $status = $target->status;
        $target->delete();
        $this->logChange($admin, $target, 'deleted', ['before' => ['status' => $status], 'after' => ['status' => 'deleted']], $reason);
    }

    /** admin.users.restore — khôi phục người dùng đã xóa mềm, về đúng trạng thái trước khi xóa (active/suspended). */
    public function restore(User $admin, User $target): User
    {
        $target->restore();
        $this->logChange($admin, $target, 'restored', ['before' => ['status' => 'deleted'], 'after' => ['status' => $target->status]]);

        return $target;
    }

    /**
     * admin.users.roles.update — gán/gỡ vai trò (4.3: 1 user có thể có nhiều vai trò). Chặn
     * admin tự gỡ Super Admin cuối cùng của hệ thống (an toàn theo mặc định — tránh khoá
     * nhầm quyền truy cập, "Super Admin luôn có mọi quyền" theo docs/ARCHITECTURE.md mục 3).
     * Ghi audit log (16 mục 4: "role" nằm trong danh sách hành động bắt buộc audit).
     */
    public function updateRoles(User $admin, User $user, array $roleNames): User
    {
        $before = $user->roles->pluck('name')->all();

        if (in_array(Role::SUPER_ADMIN, $before, true) && ! in_array(Role::SUPER_ADMIN, $roleNames, true)) {
            $remainingSuperAdmins = $this->users->query()
                ->whereHas('roles', fn ($q) => $q->where('name', Role::SUPER_ADMIN))
                ->where('id', '!=', $user->id)
                ->count();
            if ($remainingSuperAdmins === 0) {
                throw ValidationException::withMessages(['roles' => 'Không thể gỡ Super Admin cuối cùng của hệ thống.']);
            }
        }

        $roleIds = Role::whereIn('name', $roleNames)->pluck('id');
        $user->roles()->sync($roleIds);

        $after = $roleNames;
        if ($before !== $after) {
            $this->logChange($admin, $user, 'updated', ['before' => ['roles' => $before], 'after' => ['roles' => $after]]);
        }

        return $user->fresh(['roles']);
    }

    /**
     * admin.users.password.update — Admin đổi mật khẩu trực tiếp cho người dùng (note họp
     * 13/8, mục 2: "Cần có đổi mật khẩu... cho người dùng") — dùng khi người dùng quên mật
     * khẩu/mất quyền truy cập email và không tự đổi được qua "Quên mật khẩu". KHÔNG log giá
     * trị mật khẩu mới vào audit log (chỉ log hành động đã xảy ra), đúng tinh thần ghi chú ở
     * đầu file về việc không để lộ field nhạy cảm.
     */
    public function resetPassword(User $admin, User $user, string $newPassword): void
    {
        $this->users->update($user, ['password' => Hash::make($newPassword)]);
        $this->logChange($admin, $user, 'password_reset', []);
    }

    /**
     * admin.parent-links.approve — Chờ xác minh -> Đã xác minh (10.3: "xác minh phụ huynh
     * chặt chẽ"). Không yêu cầu lý do, giống TeacherApprovalService::approve(). ParentLink
     * dùng trait Auditable nên tự ghi audit log khi update() — không cần logChange() thủ công
     * ở đây (khác User, xem ghi chú đầu file về lý do User không gắn Auditable).
     */
    public function approveParentLink(ParentLink $link, User $admin): ParentLink
    {
        $this->parentLinks->update($link, [
            'status' => ParentLinkStatus::Verified->value,
            'verification_method' => 'admin_manual',
            'verified_at' => now(),
        ]);

        return $link;
    }

    /**
     * admin.parent-links.reject — Chờ xác minh -> Đã hủy (thu hồi), PHẢI có lý do (16 mục 4,
     * giống tinh thần TeacherApprovalService::reject()).
     */
    public function rejectParentLink(ParentLink $link, User $admin, string $reason): ParentLink
    {
        ParentLink::$auditReason = $reason;

        $this->parentLinks->update($link, ['status' => ParentLinkStatus::Revoked->value]);

        ParentLink::$auditReason = null;

        return $link;
    }

    private function presentUser(User $u): array
    {
        $roleLabels = $u->roles->pluck('name')->map(fn ($r) => self::ROLE_LABELS[$r] ?? $r)->all();

        $isTeacher = in_array(Role::TEACHER, $u->roles->pluck('name')->all(), true);
        $status = $isTeacher
            ? ($u->teacherProfile?->approval_status?->label() ?? 'Chưa đăng ký')
            : 'Hoạt động';
        $tone = $isTeacher && $u->teacherProfile?->approval_status?->value === 'pending' ? 'warning' : 'success';

        // Tài khoản bị khóa / đã xóa phải hiện rõ ở danh sách (trước đây cột này chỉ phản ánh duyệt giáo viên).
        if ($u->trashed()) {
            $status = 'Đã xóa';
            $tone = 'danger';
        } elseif ($u->status === 'suspended') {
            $status = 'Tạm khóa';
            $tone = 'danger';
        }

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $roleLabels,
            'status' => $status,
            'tone' => $tone,
            'created' => $u->created_at?->format('d/m/Y'),
            'trashed' => $u->trashed(),
            'is_self' => $u->id === auth()->id(),
        ];
    }

    private const FIELD_LABELS = [
        'name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại', 'province' => 'Tỉnh/Thành',
        'region' => 'Khu vực', 'status' => 'Trạng thái', 'roles' => 'Vai trò',
    ];

    private const ACTION_TITLES = [
        'created' => 'Tạo tài khoản', 'updated' => 'Cập nhật thông tin', 'locked' => 'Khóa tài khoản',
        'unlocked' => 'Mở khóa tài khoản', 'deleted' => 'Xóa người dùng', 'restored' => 'Khôi phục người dùng',
        'password_reset' => 'Đặt lại mật khẩu', 'permissions_updated' => 'Cập nhật phân quyền',
    ];

    private const REGION_LABELS = ['mien_bac' => 'Miền Bắc', 'mien_trung' => 'Miền Trung', 'mien_nam' => 'Miền Nam'];

    private const STATUS_LABELS = ['active' => 'Hoạt động', 'suspended' => 'Tạm khóa', 'deleted' => 'Đã xóa'];

    /** Giá trị một trường audit -> chữ dễ đọc. */
    private function humanValue(string $field, mixed $v): string
    {
        if (is_array($v)) {
            $v = collect($v)->map(fn ($x) => $field === 'roles' ? (self::ROLE_LABELS[$x] ?? $x) : (string) $x)->implode(', ');
        }
        if ($v === null || $v === '') {
            return 'Chưa cập nhật';
        }
        if ($field === 'status') {
            return self::STATUS_LABELS[$v] ?? (string) $v;
        }
        if ($field === 'region') {
            return self::REGION_LABELS[$v] ?? (string) $v;
        }

        return (string) $v;
    }

    /**
     * Tab "Lịch sử": audit log của người dùng -> danh sách dễ đọc. Hỗ trợ cả 2 dạng cột changes đang tồn tại:
     * {before:{...}, after:{...}} (UserService::logChange) và {field:{old,new}} (trait Auditable).
     *
     * @return array<int, array{title: string, when: string, actor: string, lines: array<int, string>, reason: ?string, tone: string}>
     */
    public function historyFor(User $user, int $limit = 200): array
    {
        $logs = $this->auditLogs->forAuditable(User::class, $user->id, $limit);

        return $logs->map(function ($log) {
            $ch = is_array($log->changes) ? $log->changes : [];
            $lines = [];

            if (isset($ch['before']) || isset($ch['after'])) {
                $b = (array) ($ch['before'] ?? []);
                $a = (array) ($ch['after'] ?? []);
                foreach (array_unique(array_merge(array_keys($b), array_keys($a))) as $f) {
                    $label = self::FIELD_LABELS[$f] ?? $f;
                    $isPerm = $log->action === 'permissions_updated';
                    $old = $isPerm ? (string) ($b[$f] ?? '—') : $this->humanValue($f, $b[$f] ?? null);
                    $new = $isPerm ? (string) ($a[$f] ?? '—') : $this->humanValue($f, $a[$f] ?? null);
                    $lines[] = $label.': '.$old.' → '.$new;
                }
            } elseif ($log->action === 'created' && isset($ch['roles'])) {
                $lines[] = 'Vai trò: '.$this->humanValue('roles', $ch['roles']);
            } else {
                foreach ($ch as $f => $v) {
                    if (is_array($v) && (array_key_exists('old', $v) || array_key_exists('new', $v))) {
                        $lines[] = (self::FIELD_LABELS[$f] ?? $f).': '.$this->humanValue($f, $v['old'] ?? null).' → '.$this->humanValue($f, $v['new'] ?? null);
                    }
                }
            }

            return [
                'title' => self::ACTION_TITLES[$log->action] ?? ucfirst($log->action),
                'when' => $log->created_at?->timezone(config('app.timezone'))->format('H:i d/m/Y') ?? '',
                'actor' => $log->actor?->name ?: ($log->actor?->email ?: 'Hệ thống'),
                'lines' => $lines,
                'reason' => $log->reason,
                'tone' => match ($log->action) {
                    'deleted', 'locked' => 'danger',
                    'restored', 'unlocked', 'created' => 'ok',
                    default => 'info',
                },
            ];
        })->all();
    }

    /** Dữ liệu cho popup chi tiết (partial admin.users._panel): 3 tab Thông tin / Phân quyền / Lịch sử. */
    public function panelData(int $userId, User $actor): array
    {
        $userModel = User::withTrashed()->with(['roles', 'teacherProfile'])->findOrFail($userId);
        $roleNames = $userModel->roles->pluck('name')->all();
        $history = $this->historyFor($userModel);

        $profile = null;
        if (in_array(Role::TEACHER, $roleNames, true)) {
            $profile = 'Giáo viên · '.($userModel->teacherProfile?->approval_status?->label() ?? 'Chưa đăng ký hồ sơ');
        }

        return [
            'u' => $userModel,
            'roleNames' => $roleNames,
            'availableRoles' => self::ROLE_LABELS,
            'roleLabels' => collect($roleNames)->map(fn ($r) => self::ROLE_LABELS[$r] ?? $r)->all(),
            'isSelf' => $userModel->id === $actor->id,
            'trashed' => $userModel->trashed(),
            'matrix' => $this->permissionService->matrix($userModel, $actor),
            'scopes' => UserPermissionService::SCOPES,
            'history' => $history,
            'profileLine' => $profile,
            'regions' => self::REGION_LABELS,
        ];
    }
}
