<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\ParentLink;
use App\Models\Role;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
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
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
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
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.users.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Học sinh', 'href' => route('admin.users.index', ['tab' => 'student']), 'active' => $tab === 'student', 'count' => $counts['student']],
            ['label' => 'Giáo viên', 'href' => route('admin.users.index', ['tab' => 'teacher']), 'active' => $tab === 'teacher', 'count' => $counts['teacher']],
            ['label' => 'Phụ huynh', 'href' => route('admin.users.index', ['tab' => 'parent']), 'active' => $tab === 'parent', 'count' => $counts['parent']],
            ['label' => 'Admin/Editor', 'href' => route('admin.users.index', ['tab' => 'staff']), 'active' => $tab === 'staff', 'count' => $counts['staff']],
        ];

        $query = $this->users->withRolesAndTeacherProfile();
        if ($tab === 'staff') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN]));
        } elseif (isset($roleMap[$tab])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleMap[$tab]));
        }

        $total = (clone $query)->count();
        $users = $query->latest()->limit(50)->get()->map(fn ($u) => $this->presentUser($u))->all();

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
        $before = ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'status' => $user->status];

        $this->users->update($user, [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ]);

        $after = ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'status' => $user->status];
        $changed = array_diff_assoc($after, $before);
        if (! empty($changed)) {
            $this->logChange($admin, $user, 'updated', ['before' => array_intersect_key($before, $changed), 'after' => $changed], $reason);
        }

        return $user;
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

    private function presentUser(User $u): array
    {
        $roleLabels = $u->roles->pluck('name')->map(fn ($r) => self::ROLE_LABELS[$r] ?? $r)->all();

        $isTeacher = in_array(Role::TEACHER, $u->roles->pluck('name')->all(), true);
        $status = $isTeacher
            ? ($u->teacherProfile?->approval_status?->label() ?? 'Chưa đăng ký')
            : 'Hoạt động';
        $tone = $isTeacher && $u->teacherProfile?->approval_status?->value === 'pending' ? 'warning' : 'success';

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $roleLabels,
            'status' => $status,
            'tone' => $tone,
            'created' => $u->created_at?->format('d/m/Y'),
        ];
    }
}
