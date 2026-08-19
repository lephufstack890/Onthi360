<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

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
