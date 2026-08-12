<?php

namespace App\Repositories\Contracts;

use App\Models\ClassEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ClassEnrollmentRepositoryInterface extends BaseRepositoryInterface
{

    public function activeForUser(int $userId, array $with = []): Collection;

    public function activeClassRoomIdsForUser(int $userId): array;

    public function findActiveForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment;

    public function existsActiveForUserAndClassRoom(int $userId, int $classRoomId): bool;
}
