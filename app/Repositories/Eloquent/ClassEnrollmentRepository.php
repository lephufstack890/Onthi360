<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassEnrollment;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClassEnrollmentRepository extends EloquentRepository implements ClassEnrollmentRepositoryInterface
{
    protected string $modelClass = ClassEnrollment::class;

    public function activeForUser(int $userId, array $with = []): Collection
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('status', 'active')
            ->with($with)
            ->get();
    }

    public function activeClassRoomIdsForUser(int $userId): array
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('status', 'active')
            ->pluck('class_room_id')
            ->all();
    }

    public function findActiveForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->where('status', 'active')
            ->first();
    }

    public function existsActiveForUserAndClassRoom(int $userId, int $classRoomId): bool
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->where('status', 'active')
            ->exists();
    }

    public function findAnyForUserAndClassRoom(int $userId, int $classRoomId): ?ClassEnrollment
    {
        return $this->query()
            ->where('student_id', $userId)
            ->where('class_room_id', $classRoomId)
            ->first();
    }
}
