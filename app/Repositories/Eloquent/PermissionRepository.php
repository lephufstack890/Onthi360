<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PermissionRepository extends EloquentRepository implements PermissionRepositoryInterface
{
    protected string $modelClass = Permission::class;

    public function findBySlug(string $slug): ?Permission
    {
        return $this->query()->where('slug', $slug)->first();
    }

    public function allGroupedByGroup(): Collection
    {
        return $this->query()->orderBy('group')->orderBy('label')->get()->groupBy('group');
    }
}
