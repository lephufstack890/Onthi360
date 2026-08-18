<?php

namespace App\Repositories\Eloquent;

use App\Models\LeaderboardEntry;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeaderboardEntryRepository extends EloquentRepository implements LeaderboardEntryRepositoryInterface
{
    protected string $modelClass = LeaderboardEntry::class;

    public function distinctClassRoomIdsForScope(string $scope): array
    {
        return $this->query()->where('scope', $scope)->select('class_room_id')->distinct()->pluck('class_room_id')->all();
    }

    public function countForClassRoomScope(int $classRoomId, string $scope): int
    {
        return $this->query()->where('scope', $scope)->where('class_room_id', $classRoomId)->count();
    }

    public function entriesForCompetition(int $competitionId): Collection
    {
        return $this->query()->with('user')
            ->where('scope', 'competition')
            ->where('competition_id', $competitionId)
            ->orderBy('rank')
            ->get();
    }

    public function entriesForClassRoom(int $classRoomId): Collection
    {
        return $this->query()->with('user')
            ->where('scope', 'class_room')
            ->where('class_room_id', $classRoomId)
            ->orderBy('rank')
            ->get();
    }
}
