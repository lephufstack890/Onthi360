<?php

namespace App\Repositories\Eloquent;

use App\Models\AccessRight;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AccessRightRepository extends EloquentRepository implements AccessRightRepositoryInterface
{
    protected string $modelClass = AccessRight::class;

    public function forUserWithProduct(int $userId): Collection
    {
        return $this->query()->where('user_id', $userId)->with('product')->get();
    }

    public function latestWithUserAndProduct(int $limit = 50): Collection
    {
        return $this->query()->with(['user', 'product'])->latest()->limit($limit)->get();
    }
}
