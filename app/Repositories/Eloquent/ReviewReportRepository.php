<?php

namespace App\Repositories\Eloquent;

use App\Models\ReviewReport;
use App\Repositories\Contracts\ReviewReportRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewReportRepository extends EloquentRepository implements ReviewReportRepositoryInterface
{
    protected string $modelClass = ReviewReport::class;

    public function countForPublishedReviews(): int
    {
        return $this->query()->whereHas('review', fn (Builder $q) => $q->where('status', 'published'))->count();
    }
}
