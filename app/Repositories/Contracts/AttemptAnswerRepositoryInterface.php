<?php

namespace App\Repositories\Contracts;

use App\Models\AttemptAnswer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AttemptAnswerRepositoryInterface extends BaseRepositoryInterface
{

    public function forQuestionAndUser(int $questionId, int $userId, int $limit = 10): Collection;

    public function questionIdsForAttempt(int $attemptId): array;
}
