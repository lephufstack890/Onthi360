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

    public function entriesForCompetitionExam(int $competitionExamId): Collection
    {
        return $this->query()->with('user')
            ->where('scope', 'competition_exam')
            ->where('competition_exam_id', $competitionExamId)
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

    /** Xem LeaderboardEntryRepositoryInterface::statsForCompetitionExams(). */
    public function statsForCompetitionExams(array $competitionExamIds): Collection
    {
        if ($competitionExamIds === []) {
            return new Collection();
        }

        return $this->query()
            ->where('scope', 'competition_exam')
            ->whereIn('competition_exam_id', $competitionExamIds)
            ->selectRaw('competition_exam_id, MAX(score) as max_score, COUNT(*) as participants')
            ->groupBy('competition_exam_id')
            ->get();
    }

    /**
     * Xem LeaderboardEntryRepositoryInterface::entriesForCompetitions().
     * `rank` là từ khoá của MySQL 8 nên phải bọc backtick khi xếp thứ tự bằng SQL thô; dòng
     * chưa được chấm hạng (rank NULL) đẩy xuống cuối thay vì lên đầu như mặc định của MySQL.
     */
    public function entriesForCompetitions(array $competitionIds): Collection
    {
        if ($competitionIds === []) {
            return new Collection();
        }

        return $this->query()
            ->with('user:id,name')
            ->where('scope', 'competition')
            ->whereIn('competition_id', $competitionIds)
            ->orderByRaw('`rank` IS NULL')
            ->orderBy('rank')
            ->orderByDesc('score')
            ->get();
    }
}
