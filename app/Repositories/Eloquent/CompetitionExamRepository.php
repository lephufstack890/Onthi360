<?php

namespace App\Repositories\Eloquent;

use App\Models\CompetitionExam;
use App\Repositories\Contracts\CompetitionExamRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CompetitionExamRepository extends EloquentRepository implements CompetitionExamRepositoryInterface
{
    protected string $modelClass = CompetitionExam::class;

    public function forCompetition(int $competitionId): Collection
    {
        return $this->query()->with('assessment')
            ->where('competition_id', $competitionId)
            ->orderBy('order')
            ->get();
    }

    public function allWithCounts(): Collection
    {
        return $this->query()->with(['competition', 'assessment'])->withCount('leaderboardEntries')->get();
    }
}
