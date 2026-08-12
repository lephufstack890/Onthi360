<?php

namespace App\Repositories\Eloquent;

use App\Models\AttemptAnswer;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AttemptAnswerRepository extends EloquentRepository implements AttemptAnswerRepositoryInterface
{
    protected string $modelClass = AttemptAnswer::class;

    public function forQuestionAndUser(int $questionId, int $userId, int $limit = 10): Collection
    {
        return $this->query()
            ->where('question_id', $questionId)
            ->whereHas('attempt', fn (Builder $q) => $q->where('user_id', $userId))
            ->latest('graded_at')
            ->limit($limit)
            ->get();
    }

    public function questionIdsForAttempt(int $attemptId): array
    {
        return $this->query()->where('attempt_id', $attemptId)->pluck('question_id')->all();
    }
}
