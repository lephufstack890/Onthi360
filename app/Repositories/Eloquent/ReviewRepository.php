<?php

namespace App\Repositories\Eloquent;

use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewRepository extends EloquentRepository implements ReviewRepositoryInterface
{
    protected string $modelClass = Review::class;

    public function publishedForTarget($targetType, int $targetId, int $limit = 30): Collection
    {
        return $this->query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('status', 'published')
            ->with('reviewer')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function byReviewer(int $reviewerId, int $limit = 50): Collection
    {
        return $this->query()->where('reviewer_id', $reviewerId)->latest()->limit($limit)->get();
    }

    public function pendingModeration(): Collection
    {
        return $this->query()->whereIn('status', ['submitted', 'in_moderation'])->with('reviewer')->get();
    }

    public function countPendingModeration(): int
    {
        return $this->query()->whereIn('status', ['submitted', 'in_moderation'])->count();
    }

    public function countPublished(): int
    {
        return $this->query()->where('status', 'published')->count();
    }

    public function withReports(int $limit = 50): Collection
    {
        return $this->query()->whereHas('reports')->with('reviewer')->limit($limit)->get();
    }
}
