<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivationCode;
use App\Repositories\Contracts\ActivationCodeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ActivationCodeRepository extends EloquentRepository implements ActivationCodeRepositoryInterface
{
    protected string $modelClass = ActivationCode::class;

    public function latestWithOrderItemOrder(int $limit = 50): Collection
    {
        return $this->query()->with('orderItem.order')->latest()->limit($limit)->get();
    }
}
