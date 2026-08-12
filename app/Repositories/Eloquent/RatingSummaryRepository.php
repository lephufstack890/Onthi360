<?php

namespace App\Repositories\Eloquent;

use App\Models\RatingSummary;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RatingSummaryRepository extends EloquentRepository implements RatingSummaryRepositoryInterface
{
    protected string $modelClass = RatingSummary::class;

    public function findForTarget($targetType, int $targetId): ?RatingSummary
    {
        return $this->query()->where('target_type', $targetType)->where('target_id', $targetId)->first();
    }
}
