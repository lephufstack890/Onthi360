<?php

namespace App\Policies;

use App\Models\ClassRoom;
use App\Models\Role;
use App\Models\User;

class ClassRoomPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::TEACHER) && $user->isTeacherApproved()
            || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }

    public function update(User $user, ClassRoom $classRoom): bool
    {
        return $classRoom->isTaughtBy($user) || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN) || $user->isRealAdmin(); // SỬA 3/11 — admin thật (kể cả đang xem như Giáo viên) quản lý được mọi lớp
    }

    public function view(User $user, ClassRoom $classRoom): bool
    {
        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN) || $user->isRealAdmin() || $classRoom->isTaughtBy($user)) { // SỬA 3/11 — admin thật (kể cả đang xem như Giáo viên) quản lý được mọi lớp
            return true;
        }

        return $classRoom->students()->where('users.id', $user->id)->exists();
    }
}
