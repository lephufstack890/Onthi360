<?php

namespace App\Repositories\Contracts;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface CompetitionRepositoryInterface extends BaseRepositoryInterface
{

    public function latest(int $limit = 50): Collection;

    public function withLeaderboardCounts(int $limit = 20): Collection;
}
