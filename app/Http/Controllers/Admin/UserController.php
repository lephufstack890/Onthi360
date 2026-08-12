<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /** admin.users.index (ADM-02) — 10.4 + 3.1/3.2. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        $roleMap = [
            'student' => Role::STUDENT,
            'teacher' => Role::TEACHER,
            'parent' => Role::PARENT,
        ];

        $counts = [
            'all' => User::count(),
            'student' => User::whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))->count(),
            'teacher' => User::whereHas('roles', fn ($q) => $q->where('name', Role::TEACHER))->count(),
            'parent' => User::whereHas('roles', fn ($q) => $q->where('name', Role::PARENT))->count(),
            'staff' => User::whereHas('roles', fn ($q) => $q->whereIn('name', [Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN]))->count(),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('admin.users.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Học sinh', 'href' => route('admin.users.index', ['tab' => 'student']), 'active' => $tab === 'student', 'count' => $counts['student']],
            ['label' => 'Giáo viên', 'href' => route('admin.users.index', ['tab' => 'teacher']), 'active' => $tab === 'teacher', 'count' => $counts['teacher']],
            ['label' => 'Phụ huynh', 'href' => route('admin.users.index', ['tab' => 'parent']), 'active' => $tab === 'parent', 'count' => $counts['parent']],
            ['label' => 'Admin/Editor', 'href' => route('admin.users.index', ['tab' => 'staff']), 'active' => $tab === 'staff', 'count' => $counts['staff']],
        ];

        $query = User::with('roles', 'teacherProfile');
        if ($tab === 'staff') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::EDITOR, Role::ADMIN, Role::SUPER_ADMIN]));
        } elseif (isset($roleMap[$tab])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleMap[$tab]));
        }

        $total = (clone $query)->count();
        $users = $query->latest()->limit(50)->get()->map(function ($u) {
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
        })->all();

        return view('admin.users.index', ['tab' => $tab, 'tabs' => $tabs, 'users' => $users, 'total' => $total]);
    }

    /** admin.users.show — 3.1/3.2 (đa vai trò) + audit log. */
    public function show(Request $request, int $user): View
    {
        $userModel = User::with('roles', 'teacherProfile')->findOrFail($user);

        $availableRoles = [
            Role::STUDENT => 'Học sinh',
            Role::PARENT => 'Phụ huynh',
            Role::TEACHER => 'Giáo viên',
            Role::EDITOR => 'Editor',
            Role::ADMIN => 'Admin',
            Role::SUPER_ADMIN => 'Super Admin',
        ];

        $auditLogs = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $userModel->id)
            ->with('actor')
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.users.show', [
            'userModel' => $userModel,
            'availableRoles' => $availableRoles,
            'auditLogs' => $auditLogs,
        ]);
    }
}
