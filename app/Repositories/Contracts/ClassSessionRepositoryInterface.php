<?php

namespace App\Repositories\Contracts;

use App\Models\ClassSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ClassSessionRepositoryInterface extends BaseRepositoryInterface
{

    public function nextUpcomingForClassRoom(int $classRoomId): ?ClassSession;

    public function allForClassRoom(int $classRoomId): Collection;

    public function countPastForClassRoom(int $classRoomId): int;

    public function upcomingForClassRoomIds(array $classRoomIds, int $limit = 5): Collection;

    public function allForClassRoomIds(array $classRoomIds): Collection;

    public function mostRecentPastForClassRoomIds(array $classRoomIds, int $limit = 5): Collection;

    public function currentlyInProgressForClassRoomIds(array $classRoomIds): Collection;

    public function sessionProgressCountsForClassRoomIds(array $classRoomIds): Collection;
}
