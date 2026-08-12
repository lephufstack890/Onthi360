<?php

namespace App\Repositories\Eloquent;

use App\Models\Competition;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CompetitionRepository extends EloquentRepository implements CompetitionRepositoryInterface
{
    protected string $modelClass = Competition::class;

    public function latest(int $limit = 50): Collection
    {
        return $this->query()->latest('starts_at')->limit($limit)->get();
    }

    public function withLeaderboardCounts(int $limit = 20): Collection
    {
        return $this->query()->withCount('leaderboardEntries')->latest('starts_at')->limit($limit)->get();
    }
}
