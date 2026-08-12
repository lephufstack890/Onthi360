<?php

namespace App\Repositories\Contracts;

use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface MaterialRepositoryInterface extends BaseRepositoryInterface
{

    public function latestWithProduct(int $limit = 50): Collection;

    public function findWithProduct(int $id): ?Material;
}
