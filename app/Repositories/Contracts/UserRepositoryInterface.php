<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{

    public function findByEmail(string $email): ?User;

    public function withRolesAndTeacherProfile(): Builder;

    public function countByRoleName(string $roleName): int;

    public function countByRoleNames(array $roleNames): int;
}
