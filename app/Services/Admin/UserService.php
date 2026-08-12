<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;

/**
 * Gom truy vấn/nhãn cho admin.users.* (ADM-02, 10.4 + 3.1/3.2).
 */
class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditLogRepositoryInterface $auditLogs,
    ) {}

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

    /** @return array{userModel: User, availableRoles: array, auditLogs: \Illuminate\Support\Collection} */
    public function showData(int $userId): array
    {
        $userModel = $this->users->withRolesAndTeacherProfile()->findOrFail($userId);

        $availableRoles = [
            Role::STUDENT => 'Học sinh',
            Role::PARENT => 'Phụ huynh',
            Role::TEACHER => 'Giáo viên',
            Role::EDITOR => 'Editor',
            Role::ADMIN => 'Admin',
            Role::SUPER_ADMIN => 'Super Admin',
        ];

        $auditLogs = $this->auditLogs->forAuditable(User::class, $userModel->id, 20);

        return ['userModel' => $userModel, 'availableRoles' => $availableRoles, 'auditLogs' => $auditLogs];
    }

    private function presentUser(User $u): array
    {
        $roleLabels = $u->roles->pluck('name')->map(fn ($r) => match ($r) {
            Role::STUDENT => 'Học sinh',
            Role::TEACHER => 'Giáo viên',
            Role::PARENT => 'Phụ huynh',
            Role::EDITOR => 'Editor',
            Role::ADMIN => 'Admin',
            Role::SUPER_ADMIN => 'Super Admin',
            default => $r,
        })->all();

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
