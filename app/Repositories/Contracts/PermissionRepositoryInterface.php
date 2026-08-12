<?php

namespace App\Repositories\Contracts;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

interface PermissionRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySlug(string $slug): ?Permission;

    public function allGroupedByGroup(): Collection;
}
