<?php

namespace App\Repositories\Contracts;

use App\Models\Attempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AttemptRepositoryInterface extends BaseRepositoryInterface
{

    public function recentSubmittedForUser(int $userId, int $limit = 5): Collection;

    /**
     * SỬA 23/9 (khách: "lịch sử làm bài dài quá, cho phân trang") — bản PHÂN TRANG của
     * recentSubmittedForUser(). Đếm số câu sẵn (withCount) để trang Lịch sử khỏi bắn thêm
     * 1 truy vấn cho mỗi dòng tự luyện.
     */
    public function paginateSubmittedForUser(int $userId, int $perPage = 12): LengthAwarePaginator;

    public function countSubmittedForUser(int $userId): int;

    public function inProgressForUserAndAssessment(int $userId, int $assessmentId): ?Attempt;

    public function countSubmittedForUserAndAssessment(int $userId, int $assessmentId): int;

    public function countSubmittedForUserAndCompetition(int $userId, int $competitionId): int;

    public function countSubmittedForUserAndCompetitionExam(int $userId, int $competitionExamId): int;

    public function submittedCompetitionIdsForUser(int $userId, array $competitionIds): array;

    public function submittedCompetitionExamIdsForUser(int $userId, array $competitionExamIds): array;

    public function progressForUserAndAssessments(int $userId, array $assessmentIds): Collection;

    public function latestForAssignmentAndUser(int $assignmentId, int $userId): ?Attempt;

    public function withAnswersAndAssessment(int $id): ?Attempt;

    public function forAssignmentAndUserIds(int $assignmentId, array $userIds): Collection;

    public function submittedAssignmentPairsForClassRoomIds(array $classRoomIds): Collection;
}
