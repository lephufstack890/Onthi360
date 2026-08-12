<?php

namespace App\Repositories\Contracts;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AssignmentRepositoryInterface extends BaseRepositoryInterface
{

    public function forClassRoomIds(array $classRoomIds, ?string $status = null, int $limit = 30): Collection;

    public function countForClassRoomIds(array $classRoomIds, ?string $status = null): int;

    public function forClassRoomWithAssessment(int $classRoomId): Collection;

    public function draftOrScheduledForClassRoomIds(array $classRoomIds, int $limit = 10): Collection;
}
