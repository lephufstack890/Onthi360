<?php

namespace App\Repositories\Contracts;

use App\Models\RatingSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface RatingSummaryRepositoryInterface extends BaseRepositoryInterface
{

    public function findForTarget($targetType, int $targetId): ?RatingSummary;
}
