<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Role;

class UserRepository extends EloquentRepository implements UserRepositoryInterface
{
    protected string $modelClass = User::class;

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function withRolesAndTeacherProfile(): Builder
    {
        return $this->query()->with(['roles', 'teacherProfile']);
    }

    public function countByRoleName(string $roleName): int
    {
        return $this->query()->whereHas('roles', fn (Builder $q) => $q->where('name', $roleName))->count();
    }

    public function countByRoleNames(array $roleNames): int
    {
        return $this->query()->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $roleNames))->count();
    }
}
