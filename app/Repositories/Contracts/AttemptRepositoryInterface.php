<?php

namespace App\Repositories\Contracts;

use App\Models\Attempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface AttemptRepositoryInterface extends BaseRepositoryInterface
{

    public function recentSubmittedForUser(int $userId, int $limit = 5): Collection;

    public function countSubmittedForUser(int $userId): int;

    public function inProgressForUserAndAssessment(int $userId, int $assessmentId): ?Attempt;

    public function latestForAssignmentAndUser(int $assignmentId, int $userId): ?Attempt;

    public function withAnswersAndAssessment(int $id): ?Attempt;

    public function forAssignmentAndUserIds(int $assignmentId, array $userIds): Collection;
}
