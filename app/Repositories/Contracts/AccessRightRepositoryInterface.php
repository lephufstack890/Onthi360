<?php

namespace App\Repositories\Contracts;

use App\Models\AccessRight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AccessRightRepositoryInterface extends BaseRepositoryInterface
{

    public function forUserWithProduct(int $userId): Collection;

    public function latestWithUserAndProduct(int $limit = 50): Collection;
}
