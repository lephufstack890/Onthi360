<?php

namespace App\Repositories\Contracts;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ReviewRepositoryInterface extends BaseRepositoryInterface
{

    public function publishedForTarget($targetType, int $targetId, int $limit = 30): Collection;

    public function byReviewer(int $reviewerId, int $limit = 50): Collection;

    public function pendingModeration(): Collection;

    public function countPendingModeration(): int;

    public function countPublished(): int;

    public function withReports(int $limit = 50): Collection;
}
