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
        return $classRoom->isTaughtBy($user) || $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN);
    }

    public function view(User $user, ClassRoom $classRoom): bool
    {
        if ($user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN) || $classRoom->isTaughtBy($user)) {
            return true;
        }

        return $classRoom->students()->where('users.id', $user->id)->exists();
    }
}
