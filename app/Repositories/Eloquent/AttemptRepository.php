<?php

namespace App\Repositories\Eloquent;

use App\Models\Attempt;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AttemptRepository extends EloquentRepository implements AttemptRepositoryInterface
{
    protected string $modelClass = Attempt::class;

    public function recentSubmittedForUser(int $userId, int $limit = 5): Collection
    {
        return $this->query()
            ->where('user_id', $userId)
            ->whereNotNull('submitted_at')
            ->with('assessment')
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    public function countSubmittedForUser(int $userId): int
    {
        return $this->query()->where('user_id', $userId)->whereNotNull('submitted_at')->count();
    }

    public function inProgressForUserAndAssessment(int $userId, int $assessmentId): ?Attempt
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('assessment_id', $assessmentId)
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();
    }

    public function countSubmittedForUserAndAssessment(int $userId, int $assessmentId): int
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('assessment_id', $assessmentId)
            ->whereNotNull('submitted_at')
            ->count();
    }

    public function countSubmittedForUserAndCompetition(int $userId, int $competitionId): int
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('competition_id', $competitionId)
            ->whereNotNull('submitted_at')
            ->count();
    }

    public function countSubmittedForUserAndCompetitionExam(int $userId, int $competitionExamId): int
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('competition_exam_id', $competitionExamId)
            ->whereNotNull('submitted_at')
            ->count();
    }

    public function latestForAssignmentAndUser(int $assignmentId, int $userId): ?Attempt
    {
        return $this->query()
            ->where('assignment_id', $assignmentId)
            ->where('user_id', $userId)
            ->latest('submitted_at')
            ->first();
    }

    public function withAnswersAndAssessment(int $id): ?Attempt
    {
        return $this->query()->with(['answers.question', 'assessment'])->find($id);
    }

    public function forAssignmentAndUserIds(int $assignmentId, array $userIds): Collection
    {
        return $this->query()
            ->where('assignment_id', $assignmentId)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');
    }

    public function submittedAssignmentPairsForClassRoomIds(array $classRoomIds): Collection
    {
        return $this->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->whereNotNull('assignment_id')
            ->whereNotNull('submitted_at')
            ->get(['class_room_id', 'assignment_id', 'user_id']);
    }
}
