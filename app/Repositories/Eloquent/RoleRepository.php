<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository extends EloquentRepository implements RoleRepositoryInterface
{
    protected string $modelClass = Role::class;

    public function findByName(string $name): ?Role
    {
        return $this->query()->where('name', $name)->first();
    }
}
