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

    /** Số lượt ĐÃ NỘP (submitted_at khác null) của 1 user cho 1 đề — dùng để so với
     * resubmission_policy['max_attempts'] khi mở lượt làm bài mới (6.3). */
    public function countSubmittedForUserAndAssessment(int $userId, int $assessmentId): int;

    /**
     * SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi") — đếm lượt đã nộp
     * CHỈ TRONG PHẠM VI 1 cuộc thi cụ thể (competition_id ghi ở AttemptService::startOrResume()
     * lúc tạo Attempt), thay vì đếm theo assessment_id toàn cục — dùng khi đề đang được 1 cuộc
     * thi tham chiếu trực tiếp, xem AttemptService::assertResubmissionAllowed() và
     * Public\CompetitionService::hasSubmittedAttemptForCompetition().
     */
    public function countSubmittedForUserAndCompetition(int $userId, int $competitionId): int;

    /** Tương tự countSubmittedForUserAndCompetition() nhưng ở cấp kỳ thi con (CompetitionExam). */
    public function countSubmittedForUserAndCompetitionExam(int $userId, int $competitionExamId): int;

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
