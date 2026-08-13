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

    /**
     * Các lần nộp (đã nộp) gắn với một Assignment cụ thể, thuộc các lớp trong
     * $classRoomIds — dùng để tính "Hoàn thành chung" (% cặp học sinh-bài giao đã nộp ít
     * nhất 1 lần). Chỉ lấy 3 cột cần thiết, gộp/đếm distinct cặp (assignment_id,user_id) ở
     * Service (PHP) thay vì SQL thô để không phụ thuộc cú pháp riêng của từng driver DB.
     */
    public function submittedAssignmentPairsForClassRoomIds(array $classRoomIds): Collection;
}
