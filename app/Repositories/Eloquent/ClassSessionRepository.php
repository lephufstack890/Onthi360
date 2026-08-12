<?php

namespace App\Repositories\Eloquent;

use App\Models\ClassSession;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClassSessionRepository extends EloquentRepository implements ClassSessionRepositoryInterface
{
    protected string $modelClass = ClassSession::class;

    public function nextUpcomingForClassRoom(int $classRoomId): ?ClassSession
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();
    }

    public function allForClassRoom(int $classRoomId): Collection
    {
        return $this->query()->where('class_room_id', $classRoomId)->orderBy('starts_at')->get();
    }

    public function countPastForClassRoom(int $classRoomId): int
    {
        return $this->query()
            ->where('class_room_id', $classRoomId)
            ->where('starts_at', '<', now())
            ->count();
    }

    public function upcomingForClassRoomIds(array $classRoomIds, int $limit = 5): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('starts_at', '>=', now())
            ->with('classRoom')
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }
}
