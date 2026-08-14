<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

/**
 * Quyết định "/dashboard" của user hiện tại nên trỏ tới không gian nào.
 * Ưu tiên cứng admin > teacher > parent > student cho tới khi có role
 * switcher thật (4.3) để user tự chọn không gian khi có nhiều vai trò.
 *
 * Đây chỉ là logic quyết định route name; việc thật sự điều hướng/redirect
 * hoặc delegate sang controller khác (Student\DashboardController) vẫn ở
 * DashboardController — nơi đó biết cách build response HTTP.
 */
class DashboardRoutingService
{
    public const string ADMIN = 'admin';

    public const string EDITOR = 'editor';

    public const string TEACHER = 'teacher';

    public const string PARENT = 'parent';

    public const string STUDENT = 'student';

    public function primaryDashboardFor(User $user): string
    {
        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            return self::ADMIN;
        }

        // Editor (note họp 13/8, mục 5) không có quyền vào admin.dashboard — chỉ vào
        // được khu Nội dung, nên không thể dùng chung nhánh ADMIN ở trên.
        if ($user->hasRole(Role::EDITOR)) {
            return self::EDITOR;
        }

        if ($user->hasRole(Role::TEACHER)) {
            return self::TEACHER;
        }

        if ($user->hasRole(Role::PARENT)) {
            return self::PARENT;
        }

        return self::STUDENT;
    }
}
