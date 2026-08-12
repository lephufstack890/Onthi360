<?php

namespace App\Repositories\Contracts;

use App\Models\LeaderboardEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface LeaderboardEntryRepositoryInterface extends BaseRepositoryInterface
{

    public function distinctClassRoomIdsForScope(string $scope): array;

    public function countForClassRoomScope(int $classRoomId, string $scope): int;
}
